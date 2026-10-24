@extends('layouts.app')

@section('title', 'سجل العميل '.$customer->name)

@section('content')
<div class="page-heading supplier-page-heading">
    <div>
        <div class="eyebrow">سجل العميل</div>
        <h1>{{ $customer->name }}</h1>
        <p class="muted">{{ $customer->customerNumber() }} · {{ $customer->phone ?: 'لا يوجد رقم تواصل' }}</p>
    </div>
    <a class="button" href="{{ route('customers.index') }}">العودة لإدارة العملاء</a>
</div>

<section class="stats-grid customer-history-stats">
    <div class="stat-card"><span>إجمالي الطلبات</span><strong>{{ $orders->total() }}</strong></div>
    <div class="stat-card"><span>الطلبات المغلقة</span><strong>{{ $customer->orders()->where('status', \App\Models\Order::STATUS_CLOSED)->count() }}</strong></div>
    <div class="stat-card"><span>الطلبات قيد المتابعة</span><strong>{{ $orders->total() - $customer->orders()->where('status', \App\Models\Order::STATUS_CLOSED)->count() }}</strong></div>
</section>

<section class="card customer-orders-card">
    <div class="section-heading">
        <div><h2>الطلبات السابقة</h2><p class="muted">سجل طلبات العميل، من الأحدث إلى الأقدم.</p></div>
        @if(auth()->user()->hasPermission('orders.create'))
            <a class="button button-primary" href="{{ route('orders.create', ['customer_id' => $customer->id]) }}">＋ إنشاء طلب جديد لهذا العميل</a>
        @endif
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>رقم الطلب</th><th>المندوب</th><th>المنتجات</th><th>موقع التسليم</th><th>الحالة</th><th>تاريخ الإنشاء</th><th>التفاصيل</th></tr></thead>
            <tbody>
            @forelse($orders as $order)
                <tr>
                    <td data-label="رقم الطلب"><strong>{{ $order->order_number }}</strong></td>
                    <td data-label="المندوب">{{ $order->representative?->name ?? '—' }}</td>
                    <td data-label="المنتجات">
                        @foreach($order->items as $item)
                            <span class="customer-order-item">{{ $item->product_name }} — {{ number_format((float) $item->quantity, 3) }} {{ $item->unit }}</span>
                        @endforeach
                    </td>
                    <td data-label="موقع التسليم" class="cell-location">{{ $order->delivery_location }}</td>
                    <td data-label="الحالة">
                        <span class="status status-{{ $order->status }}">{{ $order->statusLabel() }}</span>
                        <small class="order-status-detail">{{ $order->statusDetailLabel() }}</small>
                    </td>
                    <td data-label="تاريخ الإنشاء">{{ $order->created_at->format('Y-m-d') }}</td>
                    <td data-label="التفاصيل" class="history-order-actions-cell">
                        <div class="history-order-actions">
                            <a class="button button-small" href="{{ route('orders.show', $order) }}">التفاصيل</a>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty-state">لا توجد طلبات سابقة لهذا العميل.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $orders->links() }}
</section>
@endsection
