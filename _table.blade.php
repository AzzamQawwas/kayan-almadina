<div class="table-wrap">
    <table class="orders-table">
        <thead><tr><th>رقم الطلب</th><th>العميل</th><th>المندوب</th><th>الموقع</th><th>الحالة</th><th>تاريخ الإنشاء</th><th>الإجراءات</th></tr></thead>
        <tbody>
        @forelse($orders as $order)
            <tr class="order-card-row">
                <td class="order-number-cell" data-label="رقم الطلب"><a class="text-link" href="{{ route('orders.show', $order) }}">{{ $order->order_number }}</a></td>
                <td class="order-customer-cell" data-label="العميل">
                    {{ $order->customer_name }}
                    @if($order->customer)<small class="order-customer-number">{{ $order->customer->customerNumber() }}</small>@endif
                </td>
                <td class="order-representative-cell" data-label="المندوب">{{ $order->representative->name }}</td>
                <td class="cell-location order-location-cell" data-label="موقع التسليم">{{ $order->delivery_location }}</td>
                <td class="order-status-cell" data-label="الحالة">
                    <span class="status status-{{ $order->status }}">{{ $order->statusLabel() }}</span>
                    <small class="order-status-detail">{{ $order->statusDetailLabel() }}</small>
                </td>
                <td class="order-date-cell" data-label="تاريخ الإنشاء">{{ $order->created_at->format('Y-m-d') }}</td>
                <td data-label="الإجراءات" class="order-table-actions-cell">
                    <div class="order-table-actions">
                        <a class="button button-small" href="{{ route('orders.show', $order) }}">التفاصيل</a>
                        @if(auth()->user()->hasPermission('orders.edit'))
                            <a class="button button-small" href="{{ route('orders.edit', $order) }}">تعديل</a>
                        @endif
                        @if(auth()->user()->hasPermission('orders.delete'))
                            <form method="post" action="{{ route('orders.destroy', $order) }}" onsubmit="return confirm('سيتم حذف الطلب {{ $order->order_number }} نهائيًا مع بنوده وسجل تغييراته. هل تريد المتابعة؟');">
                                @csrf
                                @method('delete')
                                <button class="button button-small button-danger" type="submit">حذف</button>
                            </form>
                        @endif
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="empty-state">لا توجد طلبات في هذه القائمة.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="orders-mobile-list">
    @forelse($orders as $order)
        <article class="orders-mobile-card">
            <div class="orders-mobile-card-head">
                <a class="text-link" href="{{ route('orders.show', $order) }}">{{ $order->order_number }}</a>
                <time>{{ $order->created_at->format('Y-m-d') }}</time>
            </div>
            <div class="orders-mobile-card-customer">
                <span class="orders-mobile-label">العميل</span>
                <strong>{{ $order->customer_name }}</strong>
                @if($order->customer)<small>{{ $order->customer->customerNumber() }}</small>@endif
            </div>
            <div class="orders-mobile-status">
                <span class="orders-mobile-label">الحالة</span>
                <span class="status status-{{ $order->status }}">{{ $order->statusLabel() }}</span>
                <small>{{ $order->statusDetailLabel() }}</small>
            </div>
            <div class="orders-mobile-info">
                <div><span>المندوب</span><strong>{{ $order->representative->name }}</strong></div>
                <div><span>موقع التسليم</span><strong>{{ $order->delivery_location }}</strong></div>
            </div>
            <div class="orders-mobile-actions">
                <a class="button button-small" href="{{ route('orders.show', $order) }}">التفاصيل</a>
                @if(auth()->user()->hasPermission('orders.edit'))
                    <a class="button button-small" href="{{ route('orders.edit', $order) }}">تعديل</a>
                @endif
                @if(auth()->user()->hasPermission('orders.delete'))
                    <form method="post" action="{{ route('orders.destroy', $order) }}" onsubmit="return confirm('سيتم حذف الطلب {{ $order->order_number }} نهائيًا مع بنوده وسجل تغييراته. هل تريد المتابعة؟');">
                        @csrf
                        @method('delete')
                        <button class="button button-small button-danger" type="submit">حذف</button>
                    </form>
                @endif
            </div>
        </article>
    @empty
        <div class="empty-state">لا توجد طلبات في هذه القائمة.</div>
    @endforelse
</div>
