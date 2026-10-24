@extends('layouts.app')

@section('title', 'طلب '.$order->order_number)

@section('content')
@php
    $canViewCosts = auth()->user()->hasAnyPermission(['orders.purchase', 'orders.resolve_shortage']);
@endphp
<div class="page-heading">
    <div>
        <div class="eyebrow">تفاصيل الطلب</div>
        <h1>{{ $order->order_number }}</h1>
        <p><span class="status status-{{ $order->status }}">{{ $order->statusLabel() }}</span></p>
    </div>
    <div class="order-admin-actions">
        @if(auth()->user()->hasPermission('orders.edit'))
            <a class="button button-primary" href="{{ route('orders.edit', $order) }}">تعديل الطلب</a>
        @endif
        @if(auth()->user()->hasPermission('orders.delete'))
            <form method="post" action="{{ route('orders.destroy', $order) }}" onsubmit="return confirm('سيتم حذف الطلب {{ $order->order_number }} نهائيًا مع بنوده وسجل تغييراته. هل تريد المتابعة؟');">
                @csrf
                @method('delete')
                <button class="button button-danger" type="submit">حذف الطلب</button>
            </form>
        @endif
        <a class="button" href="{{ route('orders.index') }}">العودة للطلبات</a>
    </div>
</div>

<section class="stats-grid order-overview">
    <div class="stat-card"><span>العميل</span><strong class="stat-text">{{ $order->customer_name }}</strong><small>{{ $order->customer?->customerNumber() }}{{ $order->customer_phone ? ' — '.$order->customer_phone : ' — لا يوجد رقم تواصل' }}</small></div>
    <div class="stat-card"><span>مندوب الطلب</span><strong class="stat-text">{{ $order->representative->name }}</strong><small>أُنشئ في {{ $order->created_at->format('Y-m-d H:i') }}</small></div>
    <div class="stat-card"><span>موقع التسليم</span><strong class="stat-text">{{ $order->delivery_location }}</strong></div>
    <div class="stat-card"><span>المورد</span><strong class="stat-text">{{ $order->supplier_name ?: 'لم يحدد بعد' }}</strong><small>{{ $order->supplier_reference ?: '' }}</small></div>
</section>

<section class="card">
    <div class="section-heading"><div><h2>بنود الطلب</h2><p class="muted">{{ $order->notes ?: 'لا توجد ملاحظات إضافية.' }}</p></div></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>المنتج</th><th>الوحدة</th><th>الكمية</th><th>سعر العميل / وحدة</th>@if($canViewCosts)<th>تكلفة المورد / وحدة</th>@endif<th>الناقص</th><th>المعالجة</th></tr></thead>
            <tbody>
            @foreach($order->items as $item)
                <tr>
                    <td data-label="المنتج">{{ $item->product_name }}</td>
                    <td data-label="الوحدة">{{ $item->unit }}</td>
                    <td data-label="الكمية المطلوبة">{{ number_format((float) $item->quantity, 3) }}</td>
                    <td data-label="سعر العميل / وحدة"><x-currency-symbol :amount="$item->unit_price" /></td>
                    @if($canViewCosts)<td data-label="تكلفة المورد / وحدة"><x-currency-symbol :amount="$item->supplier_unit_cost" /></td>@endif
                    <td data-label="الكمية الناقصة">{{ number_format((float) $item->missing_quantity, 3) }}</td>
                    <td data-label="معالجة النقص">
                        @if($item->shortage_resolution === 'customer')
                            <div class="resolution-display resolution-customer">
                                <span class="resolution-badge">مدبر من العميل</span>
                                <small>لا يوجد مورد أو تكلفة إضافية</small>
                            </div>
                        @elseif($item->shortage_resolution === 'alternate_supplier')
                            <div class="resolution-display resolution-alternate">
                                <span class="resolution-badge">مورد بديل</span>
                                <strong>{{ $item->replacement_supplier }}</strong>
                            @if($canViewCosts)
                                    <small class="resolution-prices">تكلفة <x-currency-symbol :amount="$item->replacement_unit_cost" /> <span>/</span> سعر العميل <x-currency-symbol :amount="$item->replacement_unit_price" /></small>
                            @endif
                            </div>
                        @elseif($item->shortage_resolution === 'same_supplier')
                            <div class="resolution-display resolution-same">
                                <span class="resolution-badge">من نفس المورد</span>
                                <strong>{{ $item->replacement_supplier }}</strong>
                            @if($canViewCosts)
                                    <small class="resolution-prices">تكلفة <x-currency-symbol :amount="$item->replacement_unit_cost" /> <span>/</span> سعر العميل <x-currency-symbol :amount="$item->replacement_unit_price" /></small>
                            @endif
                            </div>
                        @elseif((float) $item->missing_quantity > 0)
                            <span class="resolution-badge resolution-pending">بانتظار المعالجة</span>
                        @else
                            <span class="resolution-empty">لا يوجد نقص</span>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @if($canViewCosts && $order->status === 'closed')
        <div class="finance-summary finance-summary-cards">
            <div class="finance-summary-card finance-revenue">
                <span>الإيراد النهائي</span>
                <strong><x-currency-symbol :amount="$order->revenue" /></strong>
            </div>
            <div class="finance-summary-card finance-cost">
                <span>التكلفة النهائية</span>
                <strong><x-currency-symbol :amount="$order->cost" /></strong>
            </div>
            <div class="finance-summary-card finance-profit">
                <span>الربح النهائي</span>
                <strong class="text-positive"><x-currency-symbol :amount="$order->profit" /></strong>
            </div>
        </div>
    @endif
</section>

@if(auth()->user()->hasPermission('orders.purchase') && $order->status === 'purchasing')
    <section class="card workflow-card">
        <div class="eyebrow">إجراء المشتريات</div><h2>تأكيد المورد والأسعار</h2>
        <p class="muted">سجّل التكلفة الفعلية وسعر البيع النهائي المتفق عليه لكل وحدة.</p>
        <form method="post" action="{{ route('orders.purchase', $order) }}" class="stack">
            @csrf
            <div class="form-grid purchase-form-fields">
                <label><span class="purchase-field-title">اسم المورد <span class="required">*</span></span>
                    <select name="supplier_id" required>
                        <option value="">اختر المورد</option>
                        @foreach($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" @selected((string) old('supplier_id') === (string) $supplier->id || (! old('supplier_id') && $order->supplier_name === $supplier->name))>{{ $supplier->name }}</option>
                        @endforeach
                    </select>
                    @if($suppliers->isEmpty())
                        <small class="muted">أضف موردًا من قسم المشتريات قبل تأكيد الشراء.</small>
                    @endif
                </label>
                <label><span class="purchase-field-title">رقم تأكيد / مرجع المورد</span><input name="supplier_reference" value="{{ old('supplier_reference', $order->supplier_reference) }}" maxlength="255"></label>
            </div>
            <div class="table-wrap"><table>
                <thead><tr><th>المنتج</th><th>الوحدة</th><th>الكمية</th><th>تكلفة المورد / وحدة (<x-currency-symbol />)</th><th>سعر العميل / وحدة (<x-currency-symbol />)</th></tr></thead>
                <tbody>@foreach($order->items as $item)
                    <tr>
                        <td data-label="المنتج">{{ $item->product_name }}</td>
                        <td data-label="الوحدة">{{ $item->unit }}</td>
                        <td data-label="الكمية المطلوبة">{{ number_format((float) $item->quantity, 3) }}</td>
                        <td data-label="تكلفة المورد / وحدة"><input class="table-input" type="number" min="0" max="9999999999.99" step="0.01" name="items[{{ $item->id }}][supplier_unit_cost]" value="{{ old('items.'.$item->id.'.supplier_unit_cost', $item->supplier_unit_cost) }}" required></td>
                        <td data-label="سعر العميل / وحدة"><input class="table-input" type="number" min="0" max="9999999999.99" step="0.01" name="items[{{ $item->id }}][unit_price]" value="{{ old('items.'.$item->id.'.unit_price', $item->unit_price) }}" required></td>
                    </tr>
                @endforeach</tbody>
            </table></div>
            <div class="form-actions"><button class="button button-primary" type="submit">تأكيد الشراء وإعادة الطلب للمندوب</button></div>
        </form>
    </section>
@endif

@if(auth()->user()->hasAnyPermission(['orders.close', 'orders.report_shortage']) && $order->status === 'awaiting_delivery')
    <section class="card workflow-card">
        <div class="eyebrow">إجراء المندوب</div><h2>عملية التسليم</h2>
        @if($order->items->contains(fn ($item): bool => $item->shortage_resolution === 'customer'))
            <p class="notice notice-info">بعض النواقص سيتولى العميل تدبيرها؛ ستُعدّل قيمة الطلب تلقائيًا عند إغلاقه.</p>
        @endif
        <p class="muted">اختر نتيجة التسليم لتأكيد الاستلام، تحويل النقص للمشتريات، أو إغلاق الطلب بالنقص الذي دبّره العميل.</p>
        <button class="button button-primary" type="button" id="open-delivery-dialog">عملية التسليم</button>
    </section>

    <dialog class="delivery-dialog" id="delivery-dialog" aria-labelledby="delivery-dialog-title">
        <div class="delivery-dialog-heading">
            <div><h2 id="delivery-dialog-title">عملية التسليم</h2><p class="muted">اختر الحالة المناسبة للطلب.</p></div>
            <form method="dialog"><button class="button button-quiet delivery-dialog-close" aria-label="إغلاق">×</button></form>
        </div>
        <div class="delivery-choice-list" id="delivery-choice-list">
            @if(auth()->user()->hasPermission('orders.close'))
                <button class="delivery-choice" type="button" data-delivery-choice="complete">
                    <strong>استلام كامل الكمية</strong>
                    <span>إرفاق صورة تأكيد الاستلام وإغلاق الطلب.</span>
                </button>
            @endif
            @if(auth()->user()->hasPermission('orders.report_shortage'))
                <button class="delivery-choice" type="button" data-delivery-choice="purchasing">
                    <strong>يوجد نقص — إعادة للمشتريات</strong>
                    <span>تسجيل الكمية الناقصة ليتم التنسيق مع المورد ثم إعادة الطلب للمندوب.</span>
                </button>
            @endif
            @if(auth()->user()->hasPermission('orders.close'))
                <button class="delivery-choice" type="button" data-delivery-choice="customer">
                    <strong>العميل دبّر النقص من مورد آخر</strong>
                    <span>تحديد النواقص وخصم قيمتها ثم إغلاق طلب موردنا.</span>
                </button>
            @endif
        </div>
        @if(auth()->user()->hasPermission('orders.close'))
            <form method="post" action="{{ route('orders.close', $order) }}" enctype="multipart/form-data" class="stack delivery-action-panel" data-delivery-panel="complete" hidden>
                @csrf
                <input type="hidden" name="delivery_outcome" value="complete">
                <p class="notice notice-info">أكد الاستلام الكامل بعد وصول الكميات المطلوبة، بما فيها أي نواقص عالجتها المشتريات.</p>
                <label>صورة إثبات الاستلام الكامل <span class="required">*</span>
                    <input type="file" name="delivery_photo" accept="image/jpeg,image/png,image/webp" capture="environment" required>
                    <small class="muted">JPEG أو PNG أو WebP، بحد أقصى 10 ميجابايت.</small>
                </label>
                <div class="form-actions">
                    <button class="button" type="button" data-delivery-back>رجوع للحالات</button>
                    <button class="button button-primary" type="submit">تأكيد الاستلام وإغلاق الطلب</button>
                </div>
            </form>
        @endif
        @if(auth()->user()->hasPermission('orders.report_shortage'))
            <form method="post" action="{{ route('orders.shortage', $order) }}" enctype="multipart/form-data" class="stack delivery-action-panel" data-delivery-panel="purchasing" hidden>
                @csrf
                <p class="notice notice-info">سيرجع الطلب للمشتريات للتواصل مع المورد وتحديد موعد توريد النواقص، ثم يعود إليك للتأكد من استلامها.</p>
                <label>صورة توضح حالة التوريد <span class="required">*</span>
                    <input type="file" name="delivery_photo" accept="image/jpeg,image/png,image/webp" capture="environment" required>
                    <small class="muted">JPEG أو PNG أو WebP، بحد أقصى 10 ميجابايت.</small>
                </label>
                <div class="table-wrap"><table>
                    <thead><tr><th>المنتج</th><th>الوحدة</th><th>الكمية المطلوبة</th><th>الكمية الناقصة</th></tr></thead>
                    <tbody>@foreach($order->items as $item)
                        <tr>
                            <td data-label="المنتج">{{ $item->product_name }}</td>
                            <td data-label="الوحدة">{{ $item->unit }}</td>
                            <td data-label="الكمية المطلوبة">{{ number_format((float) $item->quantity, 3) }}</td>
                            <td data-label="الكمية الناقصة"><input class="table-input" type="number" min="0" max="{{ $item->quantity }}" step="0.001" name="items[{{ $item->id }}][missing_quantity]" value="0" required></td>
                        </tr>
                    @endforeach</tbody>
                </table></div>
                <div class="form-actions">
                    <button class="button" type="button" data-delivery-back>رجوع للحالات</button>
                    <button class="button button-warning" type="submit">إعادة الطلب للمشتريات</button>
                </div>
            </form>
        @endif
        @if(auth()->user()->hasPermission('orders.close'))
            <form method="post" action="{{ route('orders.close', $order) }}" enctype="multipart/form-data" class="stack delivery-action-panel" data-delivery-panel="customer" hidden>
                @csrf
                <input type="hidden" name="delivery_outcome" value="customer_shortage">
                <p class="notice notice-info">سيتم إغلاق الطلب بكمية النقص المدبرة من العميل، وخصم قيمتها وتكلفتها من الحساب.</p>
                <div class="table-wrap"><table>
                    <thead><tr><th>المنتج</th><th>الوحدة</th><th>الكمية المطلوبة</th><th>الكمية التي دبّرها العميل</th></tr></thead>
                    <tbody>@foreach($order->items as $item)
                        <tr>
                            <td data-label="المنتج">{{ $item->product_name }}</td>
                            <td data-label="الوحدة">{{ $item->unit }}</td>
                            <td data-label="الكمية المطلوبة">{{ number_format((float) $item->quantity, 3) }}</td>
                            <td data-label="الكمية الناقصة"><input class="table-input" type="number" min="0" max="{{ $item->quantity }}" step="0.001" name="items[{{ $item->id }}][missing_quantity]" value="{{ $item->missing_quantity }}" required></td>
                        </tr>
                    @endforeach</tbody>
                </table></div>
                <label>صورة توضح التوريد أو النواقص <span class="required">*</span>
                    <input type="file" name="delivery_photo" accept="image/jpeg,image/png,image/webp" capture="environment" required>
                    <small class="muted">JPEG أو PNG أو WebP، بحد أقصى 10 ميجابايت.</small>
                </label>
                <div class="form-actions">
                    <button class="button" type="button" data-delivery-back>رجوع للحالات</button>
                    <button class="button button-primary" type="submit">خصم قيمة النقص وإغلاق الطلب</button>
                </div>
            </form>
        @endif
    </dialog>
    <script>
        const deliveryDialog = document.getElementById('delivery-dialog');
        const deliveryChoices = document.getElementById('delivery-choice-list');
        if (deliveryDialog) {
            document.getElementById('open-delivery-dialog').addEventListener('click', () => deliveryDialog.showModal());
            deliveryDialog.querySelectorAll('[data-delivery-choice]').forEach((choice) => {
                choice.addEventListener('click', () => {
                    deliveryChoices.hidden = true;
                    deliveryDialog.querySelector(`[data-delivery-panel="${choice.dataset.deliveryChoice}"]`).hidden = false;
                });
            });
            deliveryDialog.querySelectorAll('[data-delivery-back]').forEach((button) => {
                button.addEventListener('click', () => {
                    deliveryDialog.querySelectorAll('[data-delivery-panel]').forEach((panel) => panel.hidden = true);
                    deliveryChoices.hidden = false;
                });
            });
        }
    </script>
@endif

@if(auth()->user()->hasPermission('orders.resolve_shortage') && $order->status === 'shortage')
    @php($primarySupplierId = $suppliers->firstWhere('name', $order->supplier_name)?->id)
    <section class="card workflow-card">
        <div class="eyebrow">معالجة النواقص</div><h2>اختر طريقة إكمال كل منتج ناقص</h2>
        <p class="muted">يمكن تأمين النقص من نفس المورد أو مورد بديل مع تسجيل التكلفة والسعر الجديد، أو أن يتولى العميل تدبيره بنفسه.</p>
        <form method="post" action="{{ route('orders.resolve-shortage', $order) }}" class="stack">
            @csrf
            @foreach($order->items->filter(fn ($item): bool => (float) $item->missing_quantity > 0) as $item)
                <fieldset class="shortage-item" data-primary-supplier-id="{{ $primarySupplierId }}">
                    <legend>{{ $item->product_name }} — ناقص {{ number_format((float) $item->missing_quantity, 3) }} {{ $item->unit }}</legend>
                    <label>المعالجة
                        <select class="shortage-resolution" name="items[{{ $item->id }}][resolution]" required>
                            <option value="">اختر طريقة المعالجة</option>
                            <option value="same_supplier">توريد النقص من نفس المورد</option>
                            <option value="alternate_supplier">توريد النقص من مورد بديل</option>
                            <option value="customer">العميل يتولى تدبير النقص</option>
                        </select>
                    </label>
                    <div class="form-grid shortage-prices">
                        <label>
                            <span class="shortage-field-label">مورد النقص</span>
                            <select class="shortage-supplier-select" name="items[{{ $item->id }}][replacement_supplier_id]" aria-describedby="supplier-help-{{ $item->id }}">
                                <option value="">اختر المورد</option>
                                <option value="customer">مدبر من العميل</option>
                                @foreach($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}" @selected((string) old('items.'.$item->id.'.replacement_supplier_id') === (string) $supplier->id || (! old('items.'.$item->id.'.replacement_supplier_id') && old('items.'.$item->id.'.resolution') === 'same_supplier' && (int) $primarySupplierId === (int) $supplier->id))>{{ $supplier->name }}</option>
                                @endforeach
                            </select>
                            <input type="hidden" class="shortage-supplier-hidden" data-field-name="items[{{ $item->id }}][replacement_supplier_id]" value="">
                            <small id="supplier-help-{{ $item->id }}" class="shortage-supplier-help">يُختار تلقائيًا عند التوريد من نفس المورد.</small>
                        </label>
                        <label>
                            <span class="shortage-field-label">تكلفة الوحدة من المورد <x-currency-symbol /></span>
                            <input
                                class="shortage-cost-input"
                                type="number"
                                name="items[{{ $item->id }}][replacement_unit_cost]"
                                min="0"
                                max="9999999999.99"
                                step="0.01"
                                data-default-cost="{{ $item->replacement_unit_cost ?? $item->supplier_unit_cost }}"
                                value="{{ old('items.'.$item->id.'.replacement_unit_cost', $item->replacement_unit_cost ?? $item->supplier_unit_cost) }}"
                            >
                        </label>
                        <label>
                            <span class="shortage-field-label">سعر الوحدة للعميل <x-currency-symbol /></span>
                            <input
                                class="shortage-price-input"
                                type="number"
                                name="items[{{ $item->id }}][replacement_unit_price]"
                                min="0"
                                max="9999999999.99"
                                step="0.01"
                                data-default-price="{{ $item->replacement_unit_price ?? $item->unit_price }}"
                                value="{{ old('items.'.$item->id.'.replacement_unit_price', $item->replacement_unit_price ?? $item->unit_price) }}"
                            >
                        </label>
                    </div>
                </fieldset>
            @endforeach
            <div class="form-actions"><button class="button button-primary" type="submit">حفظ المعالجة وإعادة الطلب للمندوب</button></div>
        </form>
    </section>
    <script>
        document.querySelectorAll('.shortage-item').forEach(function (item) {
            const resolution = item.querySelector('.shortage-resolution');
            const supplier = item.querySelector('.shortage-supplier-select');
            const hiddenSupplier = item.querySelector('.shortage-supplier-hidden');
            const supplierHelp = item.querySelector('.shortage-supplier-help');
            const costInput = item.querySelector('.shortage-cost-input');
            const priceInput = item.querySelector('.shortage-price-input');
            const primarySupplierId = item.dataset.primarySupplierId;
            if (!resolution || !supplier || !hiddenSupplier || !costInput || !priceInput || !primarySupplierId) {
                return;
            }

            function syncSupplierField() {
                const sameSupplier = resolution.value === 'same_supplier';
                const supplierReplacement = sameSupplier || resolution.value === 'alternate_supplier';
                const customerManaged = resolution.value === 'customer';
                if (sameSupplier) {
                    supplier.value = primarySupplierId;
                    supplier.disabled = true;
                    hiddenSupplier.name = hiddenSupplier.dataset.fieldName;
                    hiddenSupplier.value = primarySupplierId;
                    supplierHelp.textContent = 'تم اختيار المورد الأساسي تلقائيًا ولا يمكن تغييره لهذا الخيار.';
                } else if (customerManaged) {
                    supplier.value = 'customer';
                    supplier.disabled = true;
                    hiddenSupplier.removeAttribute('name');
                    hiddenSupplier.value = '';
                    supplierHelp.textContent = 'النقص مدبر من العميل ولا يحتاج إلى مورد.';
                    costInput.value = '0';
                    priceInput.value = '0';
                    costInput.disabled = true;
                    priceInput.disabled = true;
                } else {
                    supplier.disabled = false;
                    hiddenSupplier.removeAttribute('name');
                    hiddenSupplier.value = '';
                    supplierHelp.textContent = 'يُختار تلقائيًا عند التوريد من نفس المورد.';
                    costInput.disabled = false;
                    priceInput.disabled = false;
                }

                if (supplierReplacement && costInput.value === '') {
                    costInput.value = costInput.dataset.defaultCost || '';
                }
                if (supplierReplacement && priceInput.value === '') {
                    priceInput.value = priceInput.dataset.defaultPrice || '';
                }
            }

            resolution.addEventListener('change', syncSupplierField);
            syncSupplierField();
        });
    </script>
@endif

@if($order->deliveryPhotos->isNotEmpty())
    <section class="card">
        <div class="section-heading"><div><h2>صور التسليم والنواقص</h2><p class="muted">صور محفوظة لكل إجراء تسليم أو إبلاغ عن نقص.</p></div></div>
        <div class="delivery-photo-grid">
            @foreach($order->deliveryPhotos as $photo)
                <article class="delivery-photo-card">
                    <a href="{{ route('orders.delivery-photo', [$order, $photo]) }}" target="_blank" rel="noopener">
                        <img src="{{ route('orders.delivery-photo', [$order, $photo]) }}" alt="{{ $photo->eventLabel() }} للطلب {{ $order->order_number }}" loading="lazy">
                    </a>
                    <strong>{{ $photo->eventLabel() }}</strong>
                    <small class="muted">{{ $photo->user?->name ?? 'مستخدم محذوف' }} · {{ $photo->created_at->format('Y-m-d H:i') }}</small>
                </article>
            @endforeach
        </div>
    </section>
@endif

<section class="card">
    <div class="section-heading"><div><h2>سجل التغييرات</h2><p class="muted">توثيق إجراءات الطلب ومستخدمها.</p></div></div>
    <ol class="audit-list">
        @forelse($order->auditLogs as $log)
            <li class="audit-entry-card">
                <div class="audit-entry-heading"><div><strong class="audit-entry-title">{{ [
                    'order_created' => 'إنشاء الطلب',
                    'purchase_confirmed' => 'تأكيد الشراء والأسعار',
                    'shortage_reported' => 'الإبلاغ عن نقص',
                    'shortage_resolved' => 'معالجة النواقص',
                    'order_closed' => 'إغلاق الطلب',
                    'customer_shortage_closed' => 'إغلاق الطلب بنقص دبّره العميل',
                    'order_updated' => 'تعديل بيانات الطلب',
                ][$log->event] ?? $log->event }}</strong>
                    <span class="muted"> — {{ $log->user?->name ?? 'مستخدم محذوف' }}</span></div>
                    <time class="audit-entry-time">{{ $log->created_at->format('Y-m-d H:i') }}</time>
                </div>
                <details class="audit-entry-details">
                    <summary>عرض تفاصيل العملية</summary>
                    <div class="audit-entry-content">
                        @include('orders.partials.audit-details', ['log' => $log])
                    </div>
                </details>
            </li>
        @empty
            <li class="muted">لا توجد تغييرات مسجلة.</li>
        @endforelse
    </ol>
</section>
@endsection
