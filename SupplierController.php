<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(): View
    {
        return view('suppliers.index', [
            'suppliers' => Supplier::query()
                ->orderByDesc('active')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function show(Request $request, Supplier $supplier): View
    {
        $user = $request->user();
        $scopeToRepresentative = fn ($query) => $user->role === User::ROLE_REPRESENTATIVE
            ? $query->where('representative_id', $user->id)
            : $query;
        $deliveryOutcomes = static function (Order $order): array {
            if ($order->status !== Order::STATUS_CLOSED) {
                return ['قيد التوريد'];
            }

            $shortageItems = $order->items->filter(
                fn ($item): bool => (float) $item->missing_quantity > 0,
            );
            $outcomes = [];

            if ($shortageItems->contains(fn ($item): bool => $item->shortage_resolution === 'alternate_supplier')) {
                $outcomes[] = 'أُغلق بعد توريد النقص';
            }
            if ($shortageItems->contains(fn ($item): bool => $item->shortage_resolution === 'customer')) {
                $outcomes[] = 'أُغلق بنقص دبّره العميل';
            }

            return $outcomes ?: ['توريد كامل'];
        };
        $statusDetail = static function (Order $order, array $outcomes): string {
            if ($order->status === Order::STATUS_CLOSED) {
                return implode('، ', $outcomes);
            }
            if ($order->status === Order::STATUS_SHORTAGE) {
                return 'تم تحويل الطلب إلى المشتريات لمعالجة النقص';
            }
            if ($order->status === Order::STATUS_AWAITING_DELIVERY) {
                $hasReplacement = $order->items->contains(
                    fn ($item): bool => $item->shortage_resolution === 'alternate_supplier',
                );

                return $hasReplacement
                    ? 'أُعيد للمندوب لاستلام النواقص'
                    : 'تم تحويل الطلب إلى المندوب للتسليم';
            }

            return 'بانتظار المشتريات';
        };

        $primaryOrders = $scopeToRepresentative(
            Order::query()->where('supplier_name', $supplier->name),
        )->with(['items', 'representative'])->get();

        $assignedOrders = collect();
        foreach ($primaryOrders as $order) {
            $replacementItems = $order->items->where('replacement_supplier', $supplier->name)->values();
            $assignedOrders->put($order->id, [
                'order' => $order,
                'assignment_type' => $replacementItems->isNotEmpty() ? 'المورد الأساسي ومورد بديل' : 'المورد الأساسي',
                'items' => $order->items,
                'outcomes' => $deliveryOutcomes($order),
                'status_detail' => $statusDetail($order, $deliveryOutcomes($order)),
            ]);
        }

        $replacementOrders = $scopeToRepresentative(
            Order::query()->whereHas('items', fn ($query) => $query->where('replacement_supplier', $supplier->name)),
        )->with(['items', 'representative'])->get();

        foreach ($replacementOrders as $order) {
            if ($assignedOrders->has($order->id)) {
                continue;
            }

            $outcomes = $deliveryOutcomes($order);
            $assignedOrders->put($order->id, [
                'order' => $order,
                'assignment_type' => 'مورد بديل',
                'items' => $order->items->where('replacement_supplier', $supplier->name)->values(),
                'outcomes' => $outcomes,
                'status_detail' => $statusDetail($order, $outcomes),
            ]);
        }

        $assignedOrders = $assignedOrders
            ->sortByDesc(fn (array $assignment) => $assignment['order']->created_at)
            ->values();
        $deliveryCount = $assignedOrders->filter(
            fn (array $assignment): bool => $assignment['order']->status === Order::STATUS_CLOSED,
        )->count();
        $shortageDeliveryCount = $assignedOrders->filter(
            fn (array $assignment): bool => in_array('أُغلق بعد توريد النقص', $assignment['outcomes'], true),
        )->count();
        $customerSourcedShortageCount = $assignedOrders->filter(
            fn (array $assignment): bool => in_array('أُغلق بنقص دبّره العميل', $assignment['outcomes'], true),
        )->count();

        return view('suppliers.show', compact(
            'supplier',
            'assignedOrders',
            'deliveryCount',
            'shortageDeliveryCount',
            'customerSourcedShortageCount',
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('suppliers', 'name')],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        Supplier::create($data + ['active' => true]);

        return back()->with('success', 'تمت إضافة المورد وأصبح متاحًا للاختيار في طلبات الشراء.');
    }

    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('suppliers', 'name')->ignore($supplier->id)],
            'contact_person' => ['sometimes', 'nullable', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'active' => ['sometimes', 'required', 'boolean'],
        ]);

        $supplier->update($data);

        if (array_key_exists('active', $data)) {
            return back()->with('success', $data['active']
                ? 'تم تفعيل المورد للاختيار في طلبات الشراء.'
                : 'تم إيقاف المورد عن الطلبات الجديدة؛ تبقى بياناته في الطلبات السابقة.');
        }

        return back()->with('success', 'تم تحديث بيانات المورد.');
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        $usedInOrder = DB::table('orders')->where('supplier_name', $supplier->name)->exists()
            || DB::table('order_items')->where('replacement_supplier', $supplier->name)->exists();

        if ($usedInOrder) {
            return back()->withErrors([
                'supplier' => 'لا يمكن حذف هذا المورد لأنه مرتبط بطلب سابق. يمكنك إيقافه بدلًا من حذفه.',
            ]);
        }

        $supplier->delete();

        return back()->with('success', 'تم حذف المورد بنجاح.');
    }
}
