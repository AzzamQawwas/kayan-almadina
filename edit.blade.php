@extends('layouts.app')

@section('title', 'تعديل '.$order->order_number)

@section('content')
@php
    $oldItems = old('items', $order->items->map(fn ($item): array => [
        'id' => $item->id,
        'product_name' => $item->product_name,
        'unit' => $item->unit,
        'quantity' => $item->quantity,
        'unit_price' => $item->unit_price,
        'supplier_unit_cost' => $item->supplier_unit_cost,
        'missing_quantity' => $item->missing_quantity,
        'shortage_resolution' => $item->shortage_resolution,
        'replacement_supplier' => $item->replacement_supplier,
        'replacement_unit_cost' => $item->replacement_unit_cost,
        'replacement_unit_price' => $item->replacement_unit_price,
    ])->all());
@endphp

<div class="page-heading">
    <div>
        <div class="eyebrow">إدارة المدير</div>
        <h1>تعديل الطلب {{ $order->order_number }}</h1>
        <p class="muted">يمكن تعديل بيانات الطلب وسير التوريد بالكامل. تُحفظ نسخة قبل التعديل في سجل التغييرات.</p>
    </div>
    <a class="button" href="{{ route('orders.show', $order) }}">العودة لتفاصيل الطلب</a>
</div>

<form method="post" action="{{ route('orders.update', $order) }}" class="stack">
    @csrf
    @method('put')

    <section class="card form-grid">
        <label>اسم العميل <span class="required">*</span>
            <input name="customer_name" value="{{ old('customer_name', $order->customer_name) }}" maxlength="255" required>
        </label>
        <label>رقم التواصل
            <input name="customer_phone" value="{{ old('customer_phone', $order->customer_phone) }}" maxlength="50" inputmode="tel">
        </label>
        <label>المندوب <span class="required">*</span>
            <select name="representative_id" required>
                @foreach($representatives as $representative)
                    <option value="{{ $representative->id }}" @selected((string) old('representative_id', $order->representative_id) === (string) $representative->id)>{{ $representative->name }}{{ $representative->active ? '' : ' (موقوف)' }}</option>
                @endforeach
            </select>
        </label>
        <label>حالة الطلب <span class="required">*</span>
            <select name="status" required>
                @foreach([
                    'purchasing' => 'بانتظار المشتريات',
                    'awaiting_delivery' => 'لدى المندوب للتسليم',
                    'shortage' => 'نقص — بانتظار المشتريات',
                    'closed' => 'مغلق',
                ] as $value => $label)
                    <option value="{{ $value }}" @selected(old('status', $order->status) === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label class="field-wide">موقع التسليم <span class="required">*</span>
            <textarea name="delivery_location" rows="2" maxlength="2000" required>{{ old('delivery_location', $order->delivery_location) }}</textarea>
        </label>
        <label class="field-wide">ملاحظات
            <textarea name="notes" rows="2" maxlength="5000">{{ old('notes', $order->notes) }}</textarea>
        </label>
        <label>المورد الأساسي
            <select name="supplier_name">
                <option value="">لم يحدد بعد</option>
                @foreach($suppliers as $supplier)
                    <option value="{{ $supplier->name }}" @selected(old('supplier_name', $order->supplier_name) === $supplier->name)>{{ $supplier->name }}{{ $supplier->active ? '' : ' (متوقف)' }}</option>
                @endforeach
            </select>
        </label>
        <label>مرجع المورد
            <input name="supplier_reference" value="{{ old('supplier_reference', $order->supplier_reference) }}" maxlength="255">
        </label>
        <label>تاريخ تأكيد الشراء
            <input type="datetime-local" name="purchasing_confirmed_at" value="{{ old('purchasing_confirmed_at', $order->purchasing_confirmed_at?->format('Y-m-d\TH:i')) }}">
        </label>
        <label>تاريخ الإغلاق
            <input type="datetime-local" name="closed_at" value="{{ old('closed_at', $order->closed_at?->format('Y-m-d\TH:i')) }}">
        </label>
    </section>

    <section class="card order-edit-items">
        <div class="section-heading">
            <div><h2>المنتجات والأسعار والنواقص</h2><p class="muted">حدّث تكاليف المورد وسعر العميل وبيانات معالجة النقص لكل بند.</p></div>
            <button class="button" type="button" id="add-edit-item">＋ إضافة منتج</button>
        </div>
        <div id="edit-items" class="item-list">
            @foreach($oldItems as $index => $item)
                <div class="card order-edit-item">
                    <input type="hidden" name="items[{{ $index }}][id]" value="{{ $item['id'] ?? '' }}">
                    <label>اسم المنتج
                        <input name="items[{{ $index }}][product_name]" value="{{ $item['product_name'] ?? '' }}" maxlength="255" required>
                    </label>
                    <label>الوحدة
                        <select name="items[{{ $index }}][unit]" required>
                            @foreach($units as $unit)
                                <option value="{{ $unit }}" @selected(($item['unit'] ?? '') === $unit)>{{ $unit }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>الكمية
                        <input type="number" name="items[{{ $index }}][quantity]" value="{{ $item['quantity'] ?? '' }}" min="0.001" max="999999999.999" step="0.001" required>
                    </label>
                    <label>سعر العميل / وحدة
                        <input type="number" name="items[{{ $index }}][unit_price]" value="{{ $item['unit_price'] ?? '' }}" min="0" max="9999999999.99" step="0.01" required>
                    </label>
                    <label>تكلفة المورد / وحدة
                        <input type="number" name="items[{{ $index }}][supplier_unit_cost]" value="{{ $item['supplier_unit_cost'] ?? '0' }}" min="0" max="9999999999.99" step="0.01" required>
                    </label>
                    <label>الكمية الناقصة
                        <input type="number" name="items[{{ $index }}][missing_quantity]" value="{{ $item['missing_quantity'] ?? '0' }}" min="0" max="999999999.999" step="0.001" required>
                    </label>
                    <label>معالجة النقص
                        <select name="items[{{ $index }}][shortage_resolution]">
                            <option value="">لم تعالج / لا يوجد نقص</option>
                            <option value="customer" @selected(($item['shortage_resolution'] ?? '') === 'customer')>العميل يدبر النقص</option>
                            <option value="same_supplier" @selected(($item['shortage_resolution'] ?? '') === 'same_supplier')>توريد من نفس المورد</option>
                            <option value="alternate_supplier" @selected(($item['shortage_resolution'] ?? '') === 'alternate_supplier')>مورد بديل</option>
                        </select>
                    </label>
                    <label>المورد البديل
                        <select name="items[{{ $index }}][replacement_supplier]">
                            <option value="">لا يوجد</option>
                            @foreach($suppliers as $supplier)
                                <option value="{{ $supplier->name }}" @selected(($item['replacement_supplier'] ?? '') === $supplier->name)>{{ $supplier->name }}{{ $supplier->active ? '' : ' (متوقف)' }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>تكلفة المورد البديل / وحدة
                        <input type="number" name="items[{{ $index }}][replacement_unit_cost]" value="{{ $item['replacement_unit_cost'] ?? '' }}" min="0" max="9999999999.99" step="0.01">
                    </label>
                    <label>سعر العميل البديل / وحدة
                        <input type="number" name="items[{{ $index }}][replacement_unit_price]" value="{{ $item['replacement_unit_price'] ?? '' }}" min="0" max="9999999999.99" step="0.01">
                    </label>
                    <button class="remove-edit-item button button-danger button-small" type="button">حذف البند</button>
                </div>
            @endforeach
        </div>
    </section>

    <div class="form-actions">
        <a class="button" href="{{ route('orders.show', $order) }}">إلغاء</a>
        <button class="button button-primary" type="submit">حفظ تعديلات الطلب</button>
    </div>
</form>

<template id="edit-item-template">
    <div class="card order-edit-item">
        <input type="hidden" data-name="id" value="">
        <label>اسم المنتج<input data-name="product_name" maxlength="255" required></label>
        <label>الوحدة
            <select data-name="unit" required>
                @foreach($units as $unit)
                    <option value="{{ $unit }}">{{ $unit }}</option>
                @endforeach
            </select>
        </label>
        <label>الكمية<input data-name="quantity" type="number" min="0.001" max="999999999.999" step="0.001" value="1" required></label>
        <label>سعر العميل / وحدة<input data-name="unit_price" type="number" min="0" max="9999999999.99" step="0.01" value="0" required></label>
        <label>تكلفة المورد / وحدة<input data-name="supplier_unit_cost" type="number" min="0" max="9999999999.99" step="0.01" value="0" required></label>
        <label>الكمية الناقصة<input data-name="missing_quantity" type="number" min="0" max="999999999.999" step="0.001" value="0" required></label>
        <label>معالجة النقص
            <select data-name="shortage_resolution">
                <option value="">لم تعالج / لا يوجد نقص</option>
                <option value="customer">العميل يدبر النقص</option>
                <option value="alternate_supplier">مورد بديل</option>
            </select>
        </label>
        <label>المورد البديل
            <select data-name="replacement_supplier">
                <option value="">لا يوجد</option>
                @foreach($suppliers as $supplier)
                    <option value="{{ $supplier->name }}">{{ $supplier->name }}</option>
                @endforeach
            </select>
        </label>
        <label>تكلفة المورد البديل / وحدة<input data-name="replacement_unit_cost" type="number" min="0" max="9999999999.99" step="0.01"></label>
        <label>سعر العميل البديل / وحدة<input data-name="replacement_unit_price" type="number" min="0" max="9999999999.99" step="0.01"></label>
        <button class="remove-edit-item button button-danger button-small" type="button">حذف البند</button>
    </div>
</template>

<script>
(() => {
    const list = document.getElementById('edit-items');
    let index = {{ max(array_merge([-1], array_map('intval', array_keys($oldItems)))) + 1 }};
    document.getElementById('add-edit-item').addEventListener('click', () => {
        const row = document.getElementById('edit-item-template').content.firstElementChild.cloneNode(true);
        row.querySelectorAll('[data-name]').forEach((input) => {
            input.name = `items[${index}][${input.dataset.name}]`;
        });
        index += 1;
        list.appendChild(row);
        row.querySelector('input:not([type=hidden])').focus();
    });
    list.addEventListener('click', (event) => {
        const button = event.target.closest('.remove-edit-item');
        if (button && list.querySelectorAll('.order-edit-item').length > 1) button.closest('.order-edit-item').remove();
    });
})();
</script>
@endsection
