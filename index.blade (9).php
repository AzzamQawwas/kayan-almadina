@extends('layouts.app')

@section('title', 'إدارة الموردين')

@section('content')
<div class="page-heading supplier-page-heading">
    <div>
        <div class="eyebrow">قسم المشتريات</div>
        <h1>إدارة الموردين</h1>
        <p class="muted">سجّل الموردين وبيانات التواصل، ثم اختر المورد المسجل عند تأكيد الشراء.</p>
    </div>
    @if(auth()->user()->hasPermission('suppliers.create'))
        <button class="button button-primary" type="button" id="open-supplier-dialog">＋ إضافة مورد</button>
    @endif
</div>

@if(auth()->user()->hasPermission('suppliers.create'))
<dialog class="supplier-dialog" id="supplier-dialog" aria-labelledby="supplier-dialog-title">
    <div class="supplier-dialog-heading">
        <div><h2 id="supplier-dialog-title">إضافة مورد</h2><p class="muted">بيانات التواصل اختيارية ويمكن استكمالها لاحقًا.</p></div>
        <form method="dialog"><button class="button button-quiet supplier-dialog-close" aria-label="إغلاق">×</button></form>
    </div>
    <form method="post" action="{{ route('suppliers.store') }}" class="form-grid supplier-create-form">
        @csrf
        <label>اسم المورد <span class="required">*</span>
            <input name="name" value="{{ old('name') }}" maxlength="255" required placeholder="اسم المؤسسة أو المورد" autofocus>
        </label>
        <label>مسؤول التواصل
            <input name="contact_person" value="{{ old('contact_person') }}" maxlength="255">
        </label>
        <label>رقم التواصل
            <input name="phone" value="{{ old('phone') }}" maxlength="50" inputmode="tel">
        </label>
        <label class="field-wide">ملاحظات
            <textarea name="notes" rows="2" maxlength="2000">{{ old('notes') }}</textarea>
        </label>
        <div class="field-wide form-actions">
            <button class="button button-primary" type="submit">حفظ المورد</button>
            <button class="button" type="button" id="cancel-supplier-dialog">إلغاء</button>
        </div>
    </form>
</dialog>
@endif

<section class="card supplier-list-card">
    <div class="section-heading">
        <div><h2>الموردون المسجلون</h2><p class="muted">{{ $suppliers->where('active', true)->count() }} مورد مفعّل لطلبات الشراء الجديدة.</p></div>
    </div>
    <div class="table-wrap management-table-wrap supplier-table-wrap">
        <table>
            <thead><tr><th>المورد</th><th>مسؤول التواصل</th><th>رقم التواصل</th><th>الطلبات والتوريدات</th><th>ملاحظات</th><th>الحالة</th>@if(auth()->user()->hasPermission('suppliers.update') || auth()->user()->hasPermission('suppliers.delete'))<th>الإجراءات</th>@endif</tr></thead>
            <tbody>
            @forelse($suppliers as $supplier)
                <tr class="management-table-row supplier-table-row">
                    <td data-label="المورد"><strong>{{ $supplier->name }}</strong></td>
                    <td data-label="مسؤول التواصل">{{ $supplier->contact_person ?: '—' }}</td>
                    <td data-label="رقم التواصل">{{ $supplier->phone ?: '—' }}</td>
                    <td data-label="الطلبات والتوريدات"><a class="button button-small" href="{{ route('suppliers.show', $supplier) }}">عرض الطلبات والتوريدات</a></td>
                    <td data-label="ملاحظات">{{ $supplier->notes ?: '—' }}</td>
                    <td data-label="الحالة"><span class="status {{ $supplier->active ? 'status-closed' : 'status-shortage' }}">{{ $supplier->active ? 'مفعّل' : 'متوقف' }}</span></td>
                    @if(auth()->user()->hasPermission('suppliers.update') || auth()->user()->hasPermission('suppliers.delete'))
                    <td class="supplier-actions-cell" data-label="الإجراءات">
                        <div class="management-actions">
                    @if(auth()->user()->hasPermission('suppliers.update'))
                        <form method="post" action="{{ route('suppliers.update', $supplier) }}">
                            @csrf
                            @method('put')
                            <input type="hidden" name="active" value="{{ $supplier->active ? '0' : '1' }}">
                            <button class="button button-small {{ $supplier->active ? 'button-danger' : 'button-primary' }}" type="submit">
                                {{ $supplier->active ? 'إيقاف الاستخدام' : 'تفعيل المورد' }}
                            </button>
                        </form>
                        <button class="button button-small" type="button" data-supplier-edit-target="supplier-edit-dialog-{{ $supplier->id }}">تعديل البيانات</button>
                        <dialog class="supplier-dialog" id="supplier-edit-dialog-{{ $supplier->id }}" aria-labelledby="supplier-edit-title-{{ $supplier->id }}">
                            <div class="supplier-dialog-heading">
                                <div><h2 id="supplier-edit-title-{{ $supplier->id }}">تعديل بيانات المورد</h2><p class="muted">{{ $supplier->name }}</p></div>
                                <form method="dialog"><button class="button button-quiet supplier-dialog-close" aria-label="إغلاق">×</button></form>
                            </div>
                            <form method="post" action="{{ route('suppliers.update', $supplier) }}" class="stack supplier-edit-form">
                                @csrf
                                @method('put')
                                <input type="hidden" name="supplier_id" value="{{ $supplier->id }}">
                                <label>اسم المورد<input name="name" value="{{ old('name', $supplier->name) }}" maxlength="255" required></label>
                                <label>مسؤول التواصل<input name="contact_person" value="{{ old('contact_person', $supplier->contact_person) }}" maxlength="255"></label>
                                <label>رقم التواصل<input name="phone" value="{{ old('phone', $supplier->phone) }}" maxlength="50" inputmode="tel"></label>
                                <label>ملاحظات<textarea name="notes" rows="2" maxlength="2000">{{ old('notes', $supplier->notes) }}</textarea></label>
                                <div class="form-actions">
                                    <button class="button button-primary" type="submit">حفظ البيانات</button>
                                    <button class="button" type="button" data-close-supplier-edit>إلغاء</button>
                                </div>
                            </form>
                        </dialog>
                    @endif
                    @if(auth()->user()->hasPermission('suppliers.delete'))
                        <form method="post" action="{{ route('suppliers.destroy', $supplier) }}" onsubmit="return confirm('هل تريد حذف هذا المورد؟ إذا كان مرتبطًا بطلب سابق فلن يمكن حذفه.');">
                            @csrf
                            @method('delete')
                            <button class="button button-small button-danger unit-delete-button" type="submit">حذف المورد</button>
                        </form>
                    @endif
                        </div>
                    </td>
                    @endif
                </tr>
            @empty
                <tr><td colspan="{{ 6 + (auth()->user()->hasPermission('suppliers.update') || auth()->user()->hasPermission('suppliers.delete') ? 1 : 0) }}" class="empty-state">لا يوجد موردون مسجلون بعد. أضف موردًا ليظهر في قائمة تأكيد الشراء.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>

<script>
    const supplierDialog = document.getElementById('supplier-dialog');
    if (supplierDialog) {
        document.getElementById('open-supplier-dialog').addEventListener('click', () => supplierDialog.showModal());
        document.getElementById('cancel-supplier-dialog').addEventListener('click', () => supplierDialog.close());
        supplierDialog.addEventListener('click', (event) => {
            if (event.target === supplierDialog) supplierDialog.close();
        });
        @if($errors->any())
            supplierDialog.showModal();
        @endif
    }
    document.querySelectorAll('[data-supplier-edit-target]').forEach((button) => {
        const editDialog = document.getElementById(button.dataset.supplierEditTarget);
        button.addEventListener('click', () => editDialog.showModal());
        editDialog.querySelector('[data-close-supplier-edit]').addEventListener('click', () => editDialog.close());
        editDialog.addEventListener('click', (event) => {
            if (event.target === editDialog) editDialog.close();
        });
        @if($errors->any())
            if (editDialog.querySelector('[name="supplier_id"]').value === @json((string) old('supplier_id'))) {
                editDialog.showModal();
            }
        @endif
    });
</script>
@endsection
