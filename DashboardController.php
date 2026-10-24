<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $canViewOrders = $user->hasPermission('orders.view');
        $canViewSuppliers = $user->hasPermission('suppliers.view');
        $canViewReports = $user->hasPermission('reports.view');
        $query = Order::query();

        if ($user->role === User::ROLE_REPRESENTATIVE) {
            $query->where('representative_id', $user->id);
        }

        $orders = $canViewOrders
            ? (clone $query)->with('representative')->latest()->limit(8)->get()
            : collect();
        $counts = $canViewOrders
            ? (clone $query)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status')
            : collect();
        $closedOrders = $canViewReports
            ? (clone $query)->where('status', Order::STATUS_CLOSED)->with('items')->get()
            : collect();
        $suppliers = $canViewSuppliers
            ? Supplier::query()
                ->select('suppliers.*')
                ->selectSub(
                    DB::table('orders')
                        ->selectRaw('COUNT(*)')
                        ->whereColumn('orders.supplier_name', 'suppliers.name'),
                    'supplied_orders_count'
                )
                ->orderBy('name')
                ->get()
            : collect();

        return view('dashboard', [
            'orders' => $orders,
            'counts' => $counts,
            'suppliers' => $suppliers,
            'closedRevenue' => round($closedOrders->sum(fn (Order $order): float => $order->revenue), 2),
            'closedProfit' => round($closedOrders->sum(fn (Order $order): float => $order->profit), 2),
        ]);
    }
}
