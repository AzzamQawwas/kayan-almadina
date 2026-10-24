@php
    $canViewCosts = auth()->user()->hasAnyPermission(['orders.purchase', 'orders.resolve_shortage']);
    $details = $log->details ?? [];
    $money = fn ($amount): string => number_format((float) $amount, 2);
    $quantity = fn ($amount): string => number_format((float) $amount, 3);
    $hasDetailedItems = collect($details['items'] ?? [])
        ->contains(fn ($item): bool => is_array($item) && isset($item['product_name']));
    $resolutionLabels = [
        'customer' => 'العميل يتولى تدبير النقص',
        'same_supplier' => 'توريد من نفس المورد',
        'alternate_supplier' => 'توريد من مورد بديل',
    ];
    $snapshotLabels = [
        'customer_name' => 'اسم العميل',
        'customer_phone' => 'رقم التواصل',
        'delivery_location' => 'موقع التسليم',
        'notes' => 'ملاحظات الطلب',
        'representative_name' => 'المندوب',
        'status' => 'حالة الطلب',
        'supplier_name' => 'المورد',
        'supplier_reference' => 'مرجع المورد',
    ];
    $statusLabels = [
        'purchasing' => 'لدى المشتريات',
        'awaiting_delivery' => 'لدى المندوب للتسليم',
        'shortage' => 'نقص — بانتظار المشتريات',
        'closed' => 'مغلق',
    ];
@endphp

@if($log->event === 'order_created')
    <div class="audit-detail-grid">
        <div><span>العميل</span><strong>{{ $details['customer_name'] ?? '—' }}</strong></div>
        <div><span>رقم التواصل</span><strong>{{ ($details['customer_phone'] ?? null) ?: 'غير مسجل' }}</strong></div>
        <div><span>موقع التسليم</span><strong>{{ $details['delivery_location'] ?? '—' }}</strong></div>
        @if(!empty($details['notes']))
            <div class="audit-detail-wide"><span>ملاحظات الطلب</span><strong>{{ $details['notes'] }}</strong></div>
        @endif
    </div>
    @if($hasDetailedItems)
        <div class="table-wrap audit-detail-table-wrap">
            <table class="audit-detail-table">
                <thead><tr><th>المنتج</th><th>الوحدة</th><th>الكمية</th><th>سعر الوحدة</th><th>إجمالي البند</th></tr></thead>
                <tbody>
                    @foreach($details['items'] as $item)
                        <tr>
                            <td data-label="المنتج">{{ $item['product_name'] ?? '—' }}</td>
                            <td data-label="الوحدة">{{ $item['unit'] ?? '—' }}</td>
                            <td data-label="الكمية">{{ $quantity($item['quantity'] ?? 0) }}</td>
                            <td data-label="سعر الوحدة"><x-currency-symbol :amount="$item['unit_price'] ?? 0" /></td>
                            <td data-label="إجمالي البند"><x-currency-symbol :amount="$item['line_revenue'] ?? 0" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if(isset($details['estimated_revenue']))
            <p class="audit-detail-total">قيمة البيع التقديرية: <strong><x-currency-symbol :amount="$details['estimated_revenue']" /></strong></p>
        @endif
    @else
        <p class="audit-detail-message">تفاصيل البنود لم تكن محفوظة عند تسجيل هذه العملية.</p>
    @endif
@elseif($log->event === 'purchase_confirmed')
    <div class="audit-detail-grid">
        <div><span>المورد</span><strong>{{ $details['supplier_name'] ?? '—' }}</strong></div>
        <div><span>مرجع المورد</span><strong>{{ ($details['supplier_reference'] ?? null) ?: 'غير مسجل' }}</strong></div>
        <div><span>حالة الطلب بعد الإجراء</span><strong>أُعيد للمندوب للتسليم</strong></div>
    </div>
    @if($hasDetailedItems)
        <div class="table-wrap audit-detail-table-wrap">
            <table class="audit-detail-table">
                <thead><tr><th>المنتج</th><th>الوحدة</th><th>الكمية</th>@if($canViewCosts)<th>تكلفة الوحدة</th><th>سعر العميل</th><th>إجمالي التكلفة</th><th>إجمالي البيع</th>@endif</tr></thead>
                <tbody>
                    @foreach($details['items'] as $item)
                        <tr>
                            <td data-label="المنتج">{{ $item['product_name'] ?? '—' }}</td>
                            <td class="audit-item-unit" data-label="الوحدة">{{ $item['unit'] ?? '—' }}</td>
                            <td data-label="الكمية">{{ $quantity($item['quantity'] ?? 0) }}</td>
                            @if($canViewCosts)
                                <td data-label="تكلفة الوحدة"><x-currency-symbol :amount="$item['supplier_unit_cost'] ?? 0" /></td>
                                <td data-label="سعر العميل"><x-currency-symbol :amount="$item['unit_price'] ?? 0" /></td>
                                <td data-label="إجمالي التكلفة"><x-currency-symbol :amount="$item['line_cost'] ?? 0" /></td>
                                <td data-label="إجمالي البيع"><x-currency-symbol :amount="$item['line_revenue'] ?? 0" /></td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($canViewCosts)
            <div class="audit-detail-totals">
                <div><span>إجمالي التكلفة</span><strong><x-currency-symbol :amount="$details['estimated_cost'] ?? 0" /></strong></div>
                <div><span>قيمة البيع</span><strong><x-currency-symbol :amount="$details['estimated_revenue'] ?? 0" /></strong></div>
                <div><span>الربح المتوقع</span><strong><x-currency-symbol :amount="($details['estimated_revenue'] ?? 0) - ($details['estimated_cost'] ?? 0)" /></strong></div>
            </div>
        @endif
    @else
        <p class="audit-detail-message">تفاصيل الأسعار والكميات لم تكن محفوظة عند تسجيل هذه العملية.</p>
    @endif
@elseif(in_array($log->event, ['shortage_reported', 'shortage_resolved'], true))
    @if($log->event === 'shortage_resolved')
        <p class="audit-detail-message">{{ $details['next_step'] ?? 'تمت معالجة النواقص وإعادة الطلب للمندوب.' }}</p>
    @else
        <p class="audit-detail-message">{{ $details['next_step'] ?? 'تم تسجيل النقص وإعادة الطلب للمشتريات.' }}</p>
    @endif
    @if($hasDetailedItems)
        <div class="table-wrap audit-detail-table-wrap">
            <table class="audit-detail-table">
                <thead>
                    @if($log->event === 'shortage_reported')
                        <tr><th>المنتج</th><th>الوحدة</th><th>الكمية المطلوبة</th><th>الكمية المستلمة</th><th>الكمية الناقصة</th></tr>
                    @else
                        <tr><th>المنتج</th><th>الوحدة</th><th>الكمية الناقصة</th><th>طريقة المعالجة</th><th>تفاصيل المورد البديل</th></tr>
                    @endif
                </thead>
                <tbody>
                    @foreach($details['items'] as $item)
                        <tr>
                            <td data-label="المنتج">{{ $item['product_name'] ?? '—' }}</td>
                            <td class="audit-item-unit" data-label="الوحدة">{{ $item['unit'] ?? '—' }}</td>
                            @if($log->event === 'shortage_reported')
                                <td data-label="الكمية المطلوبة">{{ $quantity($item['ordered_quantity'] ?? 0) }}</td>
                                <td data-label="الكمية المستلمة">{{ $quantity($item['received_quantity'] ?? 0) }}</td>
                                <td data-label="الكمية الناقصة">{{ $quantity($item['missing_quantity'] ?? 0) }}</td>
                            @else
                                <td data-label="الكمية الناقصة">{{ $quantity($item['missing_quantity'] ?? 0) }}</td>
                                <td data-label="طريقة المعالجة">{{ $resolutionLabels[$item['resolution'] ?? ''] ?? '—' }}</td>
                                <td data-label="تفاصيل المورد البديل">
                                    @if(in_array($item['resolution'] ?? null, ['alternate_supplier', 'same_supplier'], true))
                                        <strong class="audit-alternate-supplier">{{ $item['replacement_supplier'] ?? '—' }}</strong>
                                    @else
                                        العميل يتولى تدبيرها
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($log->event === 'shortage_resolved')
            @php
                $alternateItems = collect($details['items'])->filter(fn ($item): bool => ($item['resolution'] ?? null) === 'alternate_supplier');
            @endphp
            @if($canViewCosts && $alternateItems->isNotEmpty())
                <div class="audit-alternate-cards">
                    @foreach($alternateItems as $item)
                        <div class="audit-alternate-card-group">
                            <div class="audit-alternate-price-grid">
                                <div class="audit-alternate-price-card is-cost">
                                    <span>تكلفة المورد البديل</span>
                                    <strong><x-currency-symbol :amount="$item['replacement_unit_cost'] ?? 0" /></strong>
                                </div>
                                <div class="audit-alternate-price-card is-price">
                                    <span>سعر العميل</span>
                                    <strong><x-currency-symbol :amount="$item['replacement_unit_price'] ?? 0" /></strong>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        @endif
    @else
        <p class="audit-detail-message">تفاصيل البنود لم تكن محفوظة عند تسجيل هذه العملية.</p>
    @endif
@elseif(in_array($log->event, ['order_closed', 'customer_shortage_closed'], true))
    @if($log->event === 'customer_shortage_closed')
        <p class="audit-detail-message">أغلق المندوب الطلب بعد أن دبّر العميل النواقص، وخُصمت قيمتها من حسابه.</p>
    @else
        <p class="audit-detail-message">تم تأكيد الاستلام وإغلاق الطلب بعد استكمال التوريد.</p>
    @endif
    @if($hasDetailedItems)
        <div class="table-wrap audit-detail-table-wrap">
            <table class="audit-detail-table">
                <thead><tr><th>المنتج</th><th>الوحدة</th><th>الكمية المطلوبة</th><th>المستلمة من موردنا</th><th>النقص</th>@if($canViewCosts)<th>خصم العميل</th>@endif</tr></thead>
                <tbody>
                    @foreach($details['items'] as $item)
                        <tr>
                            <td data-label="المنتج">{{ $item['product_name'] ?? '—' }}</td>
                            <td class="audit-item-unit" data-label="الوحدة">{{ $item['unit'] ?? '—' }}</td>
                            <td data-label="الكمية المطلوبة">{{ $quantity($item['ordered_quantity'] ?? 0) }}</td>
                            <td data-label="المستلمة من المورد">{{ $quantity($item['received_quantity'] ?? 0) }}</td>
                            <td data-label="النقص">{{ $quantity($item['missing_quantity'] ?? 0) }}</td>
                            @if($canViewCosts)
                                <td data-label="خصم العميل"><x-currency-symbol :amount="$item['deduction'] ?? 0" /></td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p class="audit-detail-message">تفاصيل البنود لم تكن محفوظة عند تسجيل هذه العملية.</p>
    @endif
    @if($canViewCosts)
    <div class="audit-detail-totals">
        @if(isset($details['deduction']))
            <div><span>خصم قيمة النقص</span><strong><x-currency-symbol :amount="$details['deduction']" /></strong></div>
        @endif
        @if(isset($details['revenue']))
            <div><span>الإيراد النهائي</span><strong><x-currency-symbol :amount="$details['revenue']" /></strong></div>
        @endif
        @if(isset($details['cost']))
            <div><span>التكلفة النهائية</span><strong><x-currency-symbol :amount="$details['cost']" /></strong></div>
        @endif
        @if(isset($details['profit']))
            <div><span>الربح النهائي</span><strong><x-currency-symbol :amount="$details['profit']" /></strong></div>
        @endif
    </div>
    @endif
@elseif($log->event === 'order_updated' && isset($details['before'], $details['after']))
    @php
        $before = $details['before'];
        $after = $details['after'];
        $changedFields = collect($snapshotLabels)->keys()->filter(fn ($key): bool => ($before[$key] ?? null) !== ($after[$key] ?? null));
    @endphp
    @if($changedFields->isNotEmpty())
        <div class="audit-change-grid">
            @foreach($changedFields as $key)
                @php
                    $beforeValue = $key === 'status' ? ($statusLabels[$before[$key] ?? ''] ?? ($before[$key] ?? '—')) : ($before[$key] ?? '—');
                    $afterValue = $key === 'status' ? ($statusLabels[$after[$key] ?? ''] ?? ($after[$key] ?? '—')) : ($after[$key] ?? '—');
                @endphp
                <div class="audit-change">
                    <strong>{{ $snapshotLabels[$key] }}</strong>
                    <span><small>قبل</small>{{ $beforeValue ?: '—' }}</span>
                    <span><small>بعد</small>{{ $afterValue ?: '—' }}</span>
                </div>
            @endforeach
        </div>
    @endif
    @if(!empty($before['items']) || !empty($after['items']))
        <div class="audit-change-grid audit-item-snapshots">
            @foreach(['before' => 'البنود قبل التعديل', 'after' => 'البنود بعد التعديل'] as $side => $heading)
                <div>
                    <h4>{{ $heading }}</h4>
                    <div class="table-wrap audit-detail-table-wrap">
                        <table class="audit-detail-table">
                            <thead><tr><th>المنتج</th><th>الوحدة</th><th>الكمية</th>@if($canViewCosts)<th>سعر العميل</th><th>تكلفة المورد</th>@endif<th>النقص</th></tr></thead>
                            <tbody>
                                @foreach($details[$side]['items'] ?? [] as $item)
                                    <tr>
                                        <td data-label="المنتج">{{ $item['product_name'] ?? '—' }} <small>{{ $item['unit'] ?? '' }}</small></td>
                                        <td class="audit-item-unit" data-label="الوحدة">{{ $item['unit'] ?? '—' }}</td>
                                        <td data-label="الكمية">{{ $quantity($item['quantity'] ?? 0) }}</td>
                                        @if($canViewCosts)
                                            <td data-label="سعر العميل"><x-currency-symbol :amount="$item['unit_price'] ?? 0" /></td>
                                            <td data-label="تكلفة المورد"><x-currency-symbol :amount="$item['supplier_unit_cost'] ?? 0" /></td>
                                        @endif
                                        <td data-label="النقص">{{ $quantity($item['missing_quantity'] ?? 0) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@else
    <p class="audit-detail-message">لا توجد تفاصيل إضافية مسجلة لهذا الإجراء.</p>
@endif
