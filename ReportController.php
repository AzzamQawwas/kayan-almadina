<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $query = Order::query()->with(['representative', 'items']);
        if (! empty($filters['from'])) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }

        $allOrders = (clone $query)->get();
        $closedOrders = $allOrders->where('status', Order::STATUS_CLOSED)->values();
        $statusCounts = $allOrders->countBy('status');
        $trend = $this->financialTrend($allOrders, $filters);
        $orders = (clone $query)
            ->where('status', Order::STATUS_CLOSED)
            ->orderByDesc('closed_at')
            ->paginate(20)
            ->withQueryString();

        return view('reports.index', [
            'orders' => $orders,
            'from' => $filters['from'] ?? '',
            'to' => $filters['to'] ?? '',
            'totalOrders' => $allOrders->count(),
            'closedCount' => $closedOrders->count(),
            'activeCount' => $allOrders->count() - $closedOrders->count(),
            'statusCounts' => $statusCounts,
            'completeDeliveryCount' => $closedOrders->filter(
                fn (Order $order): bool => ! $order->items->contains(
                    fn (OrderItem $item): bool => (float) $item->missing_quantity > 0,
                ),
            )->count(),
            'alternateSupplierCount' => $this->countClosedOrdersByResolution($closedOrders, 'alternate_supplier'),
            'sameSupplierCount' => $this->countClosedOrdersByResolution($closedOrders, 'same_supplier'),
            'customerShortageCount' => $this->countClosedOrdersByResolution($closedOrders, 'customer'),
            'revenue' => round($closedOrders->sum(fn (Order $order): float => $order->revenue), 2),
            'cost' => round($closedOrders->sum(fn (Order $order): float => $order->cost), 2),
            'profit' => round($closedOrders->sum(fn (Order $order): float => $order->profit), 2),
            'financialTrend' => $trend['data'],
            'trendGranularity' => $trend['granularity'],
            'representativeStats' => $this->representativeStats($allOrders),
            'supplierStats' => $this->supplierStats($closedOrders),
            'canViewOrders' => $request->user()->hasPermission('orders.view'),
        ]);
    }

    private function financialTrend(Collection $orders, array $filters): array
    {
        $today = CarbonImmutable::today();
        $from = isset($filters['from'])
            ? CarbonImmutable::parse($filters['from'])
            : $today->subMonths(5)->startOfMonth();
        $to = isset($filters['to']) ? CarbonImmutable::parse($filters['to']) : $today;
        $months = (($to->year - $from->year) * 12) + $to->month - $from->month;
        $days = $from->diffInDays($to);
        $granularity = $days <= 30 ? 'day' : ($months <= 11 ? 'month' : ($months <= 35 ? 'quarter' : 'year'));
        $cursor = $this->periodStart($from, $granularity);
        $lastPeriod = $this->periodStart($to, $granularity);
        $data = [];

        while ($cursor <= $lastPeriod) {
            $key = $this->periodKey($cursor, $granularity);
            $data[$key] = [
                'key' => $key,
                'label' => $this->periodLabel($cursor, $granularity),
                'revenue' => 0.0,
                'cost' => 0.0,
                'profit' => 0.0,
            ];
            $cursor = match ($granularity) {
                'day' => $cursor->addDay(),
                'month' => $cursor->addMonth(),
                'quarter' => $cursor->addMonths(3),
                default => $cursor->addYear(),
            };
        }

        foreach ($orders as $order) {
            if ($order->status !== Order::STATUS_CLOSED) {
                continue;
            }

            $orderDate = CarbonImmutable::instance($order->created_at);
            if ($orderDate < $from->startOfDay() || $orderDate > $to->endOfDay()) {
                continue;
            }

            $key = $this->periodKey($this->periodStart($orderDate, $granularity), $granularity);
            if (! isset($data[$key])) {
                continue;
            }

            $data[$key]['revenue'] += $order->revenue;
            $data[$key]['cost'] += $order->cost;
            $data[$key]['profit'] += $order->profit;
        }

        return [
            'granularity' => $granularity,
            'data' => collect($data)->map(fn (array $period): array => [
                ...$period,
                'revenue' => round($period['revenue'], 2),
                'cost' => round($period['cost'], 2),
                'profit' => round($period['profit'], 2),
            ])->values(),
        ];
    }

    private function periodStart(CarbonImmutable $date, string $granularity): CarbonImmutable
    {
        return match ($granularity) {
            'day' => $date->startOfDay(),
            'month' => $date->startOfMonth(),
            'quarter' => $date->startOfYear()->addMonths(intdiv($date->month - 1, 3) * 3),
            default => $date->startOfYear(),
        };
    }

    private function periodKey(CarbonImmutable $date, string $granularity): string
    {
        return match ($granularity) {
            'day' => $date->format('Y-m-d'),
            'month' => $date->format('Y-m'),
            'quarter' => $date->format('Y').'-Q'.(intdiv($date->month - 1, 3) + 1),
            default => $date->format('Y'),
        };
    }

    private function periodLabel(CarbonImmutable $date, string $granularity): string
    {
        return match ($granularity) {
            'day' => $date->format('d/m'),
            'month' => $date->format('m/Y'),
            'quarter' => 'ر'.(intdiv($date->month - 1, 3) + 1).' '.$date->format('Y'),
            default => $date->format('Y'),
        };
    }

    private function countClosedOrdersByResolution(Collection $orders, string $resolution): int
    {
        return $orders->filter(
            fn (Order $order): bool => $order->items->contains(
                fn (OrderItem $item): bool => (float) $item->missing_quantity > 0
                    && $item->shortage_resolution === $resolution,
            ),
        )->count();
    }

    private function representativeStats(Collection $orders): Collection
    {
        return $orders->groupBy('representative_id')
            ->map(function (Collection $representativeOrders): array {
                $closed = $representativeOrders->where('status', Order::STATUS_CLOSED);

                return [
                    'name' => $representativeOrders->first()->representative?->name ?? 'مندوب غير معروف',
                    'total' => $representativeOrders->count(),
                    'closed' => $closed->count(),
                    'active' => $representativeOrders->count() - $closed->count(),
                    'completion_rate' => $representativeOrders->isEmpty()
                        ? 0
                        : round($closed->count() / $representativeOrders->count() * 100),
                    'revenue' => round($closed->sum(fn (Order $order): float => $order->revenue), 2),
                    'profit' => round($closed->sum(fn (Order $order): float => $order->profit), 2),
                ];
            })
            ->sortByDesc('total')
            ->values();
    }

    private function supplierStats(Collection $orders): Collection
    {
        $stats = collect();

        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                $quantity = (float) $item->quantity;
                $missing = (float) $item->missing_quantity;
                $primaryQuantity = max(0, $quantity - $missing);

                if ($order->supplier_name && $primaryQuantity > 0) {
                    $this->addSupplierContribution(
                        $stats,
                        $order->supplier_name,
                        $order->id,
                        $primaryQuantity * (float) $item->unit_price,
                        $primaryQuantity * (float) $item->supplier_unit_cost,
                    );
                }

                if (in_array($item->shortage_resolution, ['same_supplier', 'alternate_supplier'], true)
                    && $item->replacement_supplier
                    && $missing > 0) {
                    $this->addSupplierContribution(
                        $stats,
                        $item->replacement_supplier,
                        $order->id,
                        $missing * (float) $item->replacement_unit_price,
                        $missing * (float) $item->replacement_unit_cost,
                    );
                }
            }
        }

        return $stats->map(function (array $supplier): array {
            return [
                'name' => $supplier['name'],
                'orders' => count($supplier['order_ids']),
                'revenue' => round($supplier['revenue'], 2),
                'cost' => round($supplier['cost'], 2),
                'profit' => round($supplier['revenue'] - $supplier['cost'], 2),
            ];
        })->sortByDesc('orders')->values();
    }

    private function addSupplierContribution(
        Collection $stats,
        string $name,
        int $orderId,
        float $revenue,
        float $cost,
    ): void {
        $supplier = $stats->get($name, [
            'name' => $name,
            'order_ids' => [],
            'revenue' => 0.0,
            'cost' => 0.0,
        ]);
        $supplier['order_ids'][$orderId] = true;
        $supplier['revenue'] += $revenue;
        $supplier['cost'] += $cost;
        $stats->put($name, $supplier);
    }
}
