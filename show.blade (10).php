@extends('layouts.app')

@section('title', 'طلبات المورد '.$supplier->name)

@section('content')
<div class="page-heading supplier-page-heading">
    <div>
        <div class="eyebrow">سجل المورد</div>
        <h1>{{ $supplier->name }}</h1>
        <p class="muted">الطلبات المسندة للمورد وعدد التوريدات المكتملة.</p>
    </div>
    <a class="button" href="{{ route('suppliers.index') }}">العودة لإدارة الموردين</a>
</div>

<section class="stats-grid supplier-order-stats">
    <div class="stat-card"><span>إجمالي الطلبات المسندة</span><strong>{{ $assignedOrders->count() }}</strong></div>
    <div class="stat-card"><span>التوريدات المكتملة</span><strong>{{ $deliveryCount }}</strong><small>عدد الطلبات التي أُغلقت بعد التسليم</small></div>
    <div class="stat-card"><span>طلبات قيد المتابعة</span><strong>{{ $assignedOrders->count() - $deliveryCount }}</strong></div>
    <div class="stat-card"><span>نواقص تم توريدها</span><strong>{{ $shortageDeliveryCount }}</strong></div>
    <div class="stat-card"><span>نواقص دبّرها العميل</span><strong>{{ $customerSourcedShortageCount }}</strong></div>
</section>

<section class="card supplier-orders-card">
    <div class="section-heading">
        <div><h2>الطلبات المسندة لهذا المورد</h2><p class="muted">يشمل الإسناد كمورد أساسي أو مورد بديل للنواقص.</p></div>
    </div>
    <div class="table-wrap supplier-history-table-wrap">
        <table>
            <thead><tr><th>رقم الطلب</th><th>العميل</th><th>المنتجات المسندة</th><th>نوع الإسناد</th><th>نتيجة التوريد</th><th>المندوب</th><th>الحالة</th><th>تاريخ الإسناد</th><th>التفاصيل</th></tr></thead>
            <tbody>
            @forelse($assignedOrders as $assignment)
                @php($order = $assignment['order'])
                <tr>
                    <td data-label="رقم الطلب"><strong>{{ $order->order_number }}</strong></td>
                    <td data-label="العميل">{{ $order->customer_name }}</td>
                    <td data-label="المنتجات المسندة">
                        @foreach($assignment['items'] as $item)
                            <span class="supplier-assigned-item">{{ $item->product_name }} — {{ number_format((float) $item->quantity, 3) }} {{ $item->unit }}
                                @if($assignment['assignment_type'] === 'مورد بديل' && (float) $item->missing_quantity > 0)
                                    <small>(النقص: {{ number_format((float) $item->missing_quantity, 3) }})</small>
                                @endif
                            </span>
                        @endforeach
                    </td>
                    <td data-label="نوع الإسناد">{{ $assignment['assignment_type'] }}</td>
                    <td data-label="نتيجة التوريد">
                        @foreach($assignment['outcomes'] as $outcome)
                            <span class="supplier-outcome">{{ $outcome }}</span>
                        @endforeach
                    </td>
                    <td data-label="المندوب">{{ $order->representative?->name ?? '—' }}</td>
                    <td data-label="الحالة">
                        <span class="status status-{{ $order->status }}">{{ $order->statusLabel() }}</span>
                        <small class="supplier-status-detail">{{ $assignment['status_detail'] }}</small>
                    </td>
                    <td data-label="تاريخ الإسناد">{{ $order->purchasing_confirmed_at?->format('Y-m-d H:i') ?? $order->created_at->format('Y-m-d H:i') }}</td>
                    <td data-label="التفاصيل" class="history-order-actions-cell">
                        <div class="history-order-actions">
                            <a class="button button-small" href="{{ route('orders.show', $order) }}">التفاصيل</a>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="empty-state">لا توجد طلبات مسندة لهذا المورد حتى الآن.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="supplier-mobile-list">
        @forelse($assignedOrders as $assignment)
            @php($order = $assignment['order'])
            <article class="supplier-mobile-card">
                <div class="supplier-mobile-card-head">
                    <strong>{{ $order->order_number }}</strong>
                    <time>{{ $order->purchasing_confirmed_at?->format('Y-m-d') ?? $order->created_at->format('Y-m-d') }}</time>
                </div>
                <div class="supplier-mobile-customer">
                    <span>العميل</span>
                    <strong>{{ $order->customer_name }}</strong>
                </div>
                <div class="supplier-mobile-status">
                    <span>الحالة</span>
                    <span class="status status-{{ $order->status }}">{{ $order->statusLabel() }}</span>
                    <small>{{ $assignment['status_detail'] }}</small>
                </div>
                <div class="supplier-mobile-info">
                    <div>
                        <span>المنتجات المسندة</span>
                        <strong>
                            @foreach($assignment['items'] as $item)
                                {{ $item->product_name }} — {{ number_format((float) $item->quantity, 3) }} {{ $item->unit }}@if(!$loop->last)، @endif
                            @endforeach
                        </strong>
                    </div>
                    <div>
                        <span>نوع الإسناد</span>
                        <strong>{{ $assignment['assignment_type'] }}</strong>
                    </div>
                    <div>
                        <span>نتيجة التوريد</span>
                        <strong>{{ implode('، ', $assignment['outcomes']) }}</strong>
                    </div>
                    <div>
                        <span>المندوب</span>
                        <strong>{{ $order->representative?->name ?? '—' }}</strong>
                    </div>
                </div>
                <div class="supplier-mobile-actions">
                    <a class="button button-small" href="{{ route('orders.show', $order) }}">التفاصيل</a>
                </div>
            </article>
        @empty
            <div class="empty-state">لا توجد طلبات مسندة لهذا المورد حتى الآن.</div>
        @endforelse
    </div>
</section>
@endsection
