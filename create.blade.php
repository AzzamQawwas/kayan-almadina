@extends('layouts.app')

@section('title', 'إنشاء طلب')

@section('content')
<div class="page-heading">
    <div><div class="eyebrow">طلب عميل جديد</div><h1>إنشاء طلب توريد</h1><p class="muted">أدخل بيانات العميل والمواد والسعر المتفق عليه، ثم حوّل الطلب للمشتريات.</p></div>
    <a class="button" href="{{ route('orders.index') }}">العودة للطلبات</a>
</div>
<form method="post" action="{{ route('orders.store') }}" class="stack">
    @csrf
    <section class="card form-grid">
        @if(auth()->user()->hasPermission('customers.view'))
            <label class="field-wide customer-search-field">العميل <span class="required">*</span>
                <input id="customer-search" type="search" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="customer-search-results" value="{{ $selectedCustomer ? $selectedCustomer->customerNumber().' — '.$selectedCustomer->name : '' }}" placeholder="ابحث باسم العميل أو رقم التواصل أو رقم العميل" required>
                <input type="hidden" name="customer_id" id="order-customer-id" value="{{ $selectedCustomer?->id }}">
                <span id="customer-search-results" class="customer-search-results" role="listbox" hidden></span>
                <small id="selected-customer-info">{{ $selectedCustomer ? $selectedCustomer->customerNumber().' — '.($selectedCustomer->phone ?: 'لا يوجد رقم تواصل') : 'يجب اختيار عميل مسجل لإكمال الطلب.' }}</small>
            </label>
        @endif
        @if(auth()->user()->hasPermission('customers.create'))
            <div class="field-wide customer-quick-add">
                <button class="button button-small" type="button" id="open-quick-customer-dialog">＋ إضافة عميل جديد بسرعة</button>
                <span class="muted">سيُحفظ العميل ويُختار تلقائيًا لهذا الطلب.</span>
            </div>
        @endif
        <label class="field-wide">موقع التسليم <span class="required">*</span><textarea name="delivery_location" rows="2" maxlength="2000" required>{{ old('delivery_location') }}</textarea></label>
        <label class="field-wide">ملاحظات<textarea name="notes" rows="2" maxlength="5000">{{ old('notes') }}</textarea></label>
    </section>
    <section class="card">
        <div class="section-heading"><div><h2>بنود الطلب</h2><p class="muted">أدخل سعر بيع الوحدة المتفق عليه مع العميل.</p></div><button class="button" type="button" id="add-item">+ إضافة منتج</button></div>
        <div id="items" class="item-list">
            @php($defaultUnit = $units->first() ?? '')
            @php($oldItems = old('items', [['product_name' => '', 'unit' => $defaultUnit, 'quantity' => '1', 'unit_price' => '']]))
            @foreach($oldItems as $index => $item)
                <div class="item-row">
                    <label>اسم المنتج <input name="items[{{ $index }}][product_name]" value="{{ $item['product_name'] ?? '' }}" required maxlength="255" placeholder="مثال: إسمنت مقاوم"></label>
                    <label>الوحدة
                        <select name="items[{{ $index }}][unit]" required>
                            <option value="">اختر الوحدة</option>
                            @foreach($units as $unit)
                                <option value="{{ $unit }}" @selected(($item['unit'] ?? $defaultUnit) === $unit)>{{ $unit }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>الكمية <input type="number" name="items[{{ $index }}][quantity]" value="{{ $item['quantity'] ?? '1' }}" min="0.001" max="999999999.999" step="0.001" required></label>
                    <label><span class="field-label-text">سعر الوحدة (<x-currency-symbol />)</span><input type="number" name="items[{{ $index }}][unit_price]" value="{{ $item['unit_price'] ?? '' }}" min="0" max="9999999999.99" step="0.01" required></label>
                    <button class="remove-item button button-danger button-small" type="button" aria-label="حذف المنتج">حذف</button>
                </div>
            @endforeach
        </div>
    </section>
    <div class="form-actions"><a class="button" href="{{ route('orders.index') }}">إلغاء</a><button class="button button-primary" type="submit">حفظ وتحويل للمشتريات</button></div>
</form>
@if(auth()->user()->hasPermission('customers.create'))
<dialog class="supplier-dialog" id="quick-customer-dialog" aria-labelledby="quick-customer-title">
    <div class="supplier-dialog-heading">
        <div><h2 id="quick-customer-title">إضافة عميل بسرعة</h2><p class="muted">أدخل الاسم ورقم التواصل، وسيُضاف العميل إلى الطلب مباشرة.</p></div>
        <form method="dialog"><button class="button button-quiet supplier-dialog-close" aria-label="إغلاق">×</button></form>
    </div>
    <form id="quick-customer-form" class="stack">
        @csrf
        <label>اسم العميل <span class="required">*</span><input name="name" maxlength="255" required></label>
        <label>رقم التواصل <span class="required">*</span><input name="phone" maxlength="50" inputmode="tel" required></label>
        <div id="quick-customer-errors" class="notice notice-error" role="alert" hidden></div>
        <div class="form-actions">
            <button class="button button-primary" type="submit">حفظ واختيار العميل</button>
            <button class="button" type="button" id="cancel-quick-customer">إلغاء</button>
        </div>
    </form>
</dialog>
@endif
<template id="item-template">
    <div class="item-row">
        <label>اسم المنتج <input data-name="product_name" required maxlength="255" placeholder="مثال: إسمنت مقاوم"></label>
        <label>الوحدة
            <select data-name="unit" required>
                <option value="">اختر الوحدة</option>
                @foreach($units as $unit)
                    <option value="{{ $unit }}" @selected($loop->first)>{{ $unit }}</option>
                @endforeach
            </select>
        </label>
        <label>الكمية <input data-name="quantity" type="number" value="1" min="0.001" max="999999999.999" step="0.001" required></label>
        <label><span class="field-label-text">سعر الوحدة (<x-currency-symbol />)</span><input data-name="unit_price" type="number" min="0" max="9999999999.99" step="0.01" required></label>
        <button class="remove-item button button-danger button-small" type="button" aria-label="حذف المنتج">حذف</button>
    </div>
</template>
<script>
(() => {
    const customerSearch = document.getElementById('customer-search');
    const customerId = document.getElementById('order-customer-id');
    const customerResults = document.getElementById('customer-search-results');
    const customerInfo = document.getElementById('selected-customer-info');
    const orderForm = document.querySelector('form[action="{{ route('orders.store') }}"]');
    let searchTimer;
    let searchSequence = 0;
    let activeResult = -1;
    let resultCustomers = [];

    const closeCustomerResults = () => {
        customerResults.hidden = true;
        customerSearch.setAttribute('aria-expanded', 'false');
        customerSearch.removeAttribute('aria-activedescendant');
    };
    const selectCustomer = (customer) => {
        customerId.value = customer.id;
        customerSearch.value = `${customer.number} — ${customer.name}`;
        customerInfo.textContent = `${customer.number} — ${customer.phone || 'لا يوجد رقم تواصل'}`;
        customerSearch.setCustomValidity('');
        closeCustomerResults();
    };
    const renderCustomerResults = (customers) => {
        resultCustomers = customers;
        activeResult = -1;
        customerResults.replaceChildren();
        if (!customers.length) {
            const empty = document.createElement('div');
            empty.className = 'customer-search-empty';
            empty.textContent = 'لا يوجد عميل مسجل بهذا الاسم. يمكنك إضافته من زر «إضافة عميل جديد بسرعة».';
            customerResults.appendChild(empty);
        } else {
            customers.forEach((customer, index) => {
                const option = document.createElement('button');
                option.type = 'button';
                option.id = `customer-result-${customer.id}`;
                option.className = 'customer-search-option';
                option.setAttribute('role', 'option');
                option.setAttribute('aria-selected', 'false');
                const identity = document.createElement('strong');
                identity.textContent = `${customer.number} — ${customer.name}`;
                const phone = document.createElement('small');
                phone.textContent = customer.phone || 'لا يوجد رقم تواصل';
                option.append(identity, phone);
                option.addEventListener('click', () => selectCustomer(customer));
                option.dataset.index = String(index);
                customerResults.appendChild(option);
            });
        }
        customerResults.hidden = false;
        customerSearch.setAttribute('aria-expanded', 'true');
    };
    const searchCustomers = async (term) => {
        const sequence = ++searchSequence;
        try {
            const url = new URL(@json(route('customers.search')), window.location.origin);
            url.searchParams.set('q', term);
            const response = await fetch(url, { headers: { 'Accept': 'application/json' } });
            if (!response.ok) throw new Error('تعذر البحث عن العملاء.');
            const result = await response.json();
            if (sequence === searchSequence) renderCustomerResults(result.customers);
        } catch (error) {
            if (sequence !== searchSequence) return;
            renderCustomerResults([]);
            customerResults.querySelector('.customer-search-empty').textContent = error.message;
        }
    };

    if (customerSearch) {
        customerSearch.addEventListener('input', () => {
            customerId.value = '';
            customerInfo.textContent = 'اختر عميلًا مسجلًا من نتائج البحث.';
            customerSearch.setCustomValidity('اختر عميلًا مسجلًا من نتائج البحث.');
            clearTimeout(searchTimer);
            const term = customerSearch.value.trim();
            if (!term) {
                closeCustomerResults();
                return;
            }
            searchTimer = setTimeout(() => searchCustomers(term), 180);
        });
        customerSearch.addEventListener('keydown', (event) => {
            const options = [...customerResults.querySelectorAll('.customer-search-option')];
            if (event.key === 'Escape') {
                closeCustomerResults();
            } else if (event.key === 'ArrowDown' && options.length) {
                event.preventDefault();
                activeResult = (activeResult + 1) % options.length;
                options.forEach((option, index) => option.setAttribute('aria-selected', String(index === activeResult)));
                customerSearch.setAttribute('aria-activedescendant', options[activeResult].id);
            } else if (event.key === 'ArrowUp' && options.length) {
                event.preventDefault();
                activeResult = (activeResult - 1 + options.length) % options.length;
                options.forEach((option, index) => option.setAttribute('aria-selected', String(index === activeResult)));
                customerSearch.setAttribute('aria-activedescendant', options[activeResult].id);
            } else if (event.key === 'Enter' && activeResult >= 0 && resultCustomers[activeResult]) {
                event.preventDefault();
                selectCustomer(resultCustomers[activeResult]);
            }
        });
        document.addEventListener('click', (event) => {
            if (!event.target.closest('.customer-search-field')) closeCustomerResults();
        });
        orderForm.addEventListener('submit', (event) => {
            if (!customerId.value) {
                event.preventDefault();
                customerSearch.setCustomValidity('اختر عميلًا مسجلًا من نتائج البحث.');
                customerSearch.reportValidity();
                customerSearch.focus();
            }
        });
        if (customerId.value) customerSearch.setCustomValidity('');
    }

    const quickDialog = document.getElementById('quick-customer-dialog');
    if (quickDialog) {
        const quickForm = document.getElementById('quick-customer-form');
        const quickErrors = document.getElementById('quick-customer-errors');
        document.getElementById('open-quick-customer-dialog').addEventListener('click', () => quickDialog.showModal());
        document.getElementById('cancel-quick-customer').addEventListener('click', () => quickDialog.close());
        quickDialog.addEventListener('click', (event) => {
            if (event.target === quickDialog) quickDialog.close();
        });
        quickForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            quickErrors.hidden = true;
            try {
                const response = await fetch(@json(route('customers.quick-store')), {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: new FormData(quickForm),
                });
                const result = await response.json();
                if (!response.ok) {
                    quickErrors.innerHTML = '<strong>تعذر حفظ العميل:</strong>';
                    const list = document.createElement('ul');
                    Object.values(result.errors || { error: ['حدث خطأ أثناء حفظ العميل.'] }).flat().forEach((message) => {
                        const item = document.createElement('li');
                        item.textContent = message;
                        list.appendChild(item);
                    });
                    quickErrors.appendChild(list);
                    quickErrors.hidden = false;
                    return;
                }
                selectCustomer(result.customer);
                quickForm.reset();
                quickDialog.close();
            } catch {
                quickErrors.textContent = 'تعذر الاتصال بالخادم. تحقق من الاتصال ثم حاول مجددًا.';
                quickErrors.hidden = false;
            }
        });
    }

    const list = document.getElementById('items');
    let index = {{ max(array_merge([-1], array_map('intval', array_keys($oldItems)))) + 1 }};
    document.getElementById('add-item').addEventListener('click', () => {
        const row = document.getElementById('item-template').content.firstElementChild.cloneNode(true);
        row.querySelectorAll('[data-name]').forEach((input) => {
            input.name = `items[${index}][${input.dataset.name}]`;
        });
        index += 1;
        list.appendChild(row);
        row.querySelector('input').focus();
    });
    list.addEventListener('click', (event) => {
        if (event.target.closest('.remove-item') && list.querySelectorAll('.item-row').length > 1) {
            event.target.closest('.item-row').remove();
        }
    });
})();
</script>
@endsection
