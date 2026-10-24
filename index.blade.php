@extends('layouts.app')

@section('title', 'إدارة العملاء')

@section('content')
<div class="page-heading supplier-page-heading">
    <div>
        <div class="eyebrow">بيانات العملاء</div>
        <h1>إدارة العملاء</h1>
        <p class="muted">احفظ بيانات العملاء لاختيارها بسرعة عند إنشاء طلب توريد جديد.</p>
    </div>
    @if(auth()->user()->hasPermission('customers.create'))
        <button class="button button-primary" type="button" id="open-customer-dialog">＋ إضافة عميل</button>
    @endif
</div>

@if(auth()->user()->hasPermission('customers.create'))
<dialog class="supplier-dialog" id="customer-dialog" aria-labelledby="customer-dialog-title">
    <div class="supplier-dialog-heading">
        <div><h2 id="customer-dialog-title">إضافة عميل</h2><p class="muted">أدخل اسم العميل ورقم التواصل.</p></div>
        <form method="dialog"><button class="button button-quiet supplier-dialog-close" aria-label="إغلاق">×</button></form>
    </div>
    <form method="post" action="{{ route('customers.store') }}" class="stack customer-form">
        @csrf
        <input type="hidden" name="_customer_form" value="create">
        <label>اسم العميل <span class="required">*</span><input name="name" value="{{ old('name') }}" maxlength="255" required></label>
        <label>رقم التواصل <span class="required">*</span><input name="phone" value="{{ old('phone') }}" maxlength="50" inputmode="tel" required></label>
        <label>ملاحظات<textarea name="notes" rows="2" maxlength="2000">{{ old('notes') }}</textarea></label>
        <div class="form-actions">
            <button class="button button-primary" type="submit">حفظ العميل</button>
            <button class="button" type="button" id="cancel-customer-dialog">إلغاء</button>
        </div>
    </form>
</dialog>
@endif

<form method="get" action="{{ route('customers.index') }}" class="filter-bar card customer-filter">
    <label for="customer-status">تصفية حسب حالة العميل</label>
    <select name="status" id="customer-status">
        <option value="" @selected($selectedStatus === '')>كل العملاء</option>
        <option value="active" @selected($selectedStatus === 'active')>العملاء المفعّلون</option>
        <option value="inactive" @selected($selectedStatus === 'inactive')>العملاء غير المفعّلين</option>
    </select>
    <button class="button" type="submit">تطبيق</button>
    @if($selectedStatus !== '')
        <a class="text-link" href="{{ route('customers.index') }}">مسح التصفية</a>
    @endif
</form>

<section class="card supplier-list-card">
    <div class="section-heading">
        <div><h2>العملاء المسجلون</h2><p class="muted">{{ $customers->total() }} عميل مسجل{{ $selectedStatus === 'active' ? ' مفعّل' : ($selectedStatus === 'inactive' ? ' غير مفعّل' : '') }}.</p></div>
    </div>
    <div class="table-wrap management-table-wrap customer-table-wrap">
        <table>
            <thead>
                <tr><th>رقم العميل</th><th>اسم العميل</th><th>رقم التواصل</th><th>الطلبات</th><th>الحالة</th>
                    @if(auth()->user()->hasPermission('customers.update') || auth()->user()->hasPermission('customers.delete'))<th>الإجراءات</th>@endif
                </tr>
            </thead>
            <tbody>
            @forelse($customers as $customer)
                <tr class="customer-row">
                    <td class="customer-number-cell" data-label="رقم العميل"><strong>{{ $customer->customerNumber() }}</strong></td>
                    <td class="customer-name-cell" data-label="اسم العميل"><a class="text-link" href="{{ route('customers.show', $customer) }}">{{ $customer->name }}</a></td>
                    <td class="customer-phone-cell" data-label="رقم التواصل">{{ $customer->phone ?: '—' }}</td>
                    <td class="customer-orders-cell" data-label="الطلبات">{{ $customer->orders_count }}</td>
                    <td class="customer-status-cell" data-label="الحالة"><span class="status {{ $customer->active ? 'status-closed' : 'status-shortage' }}">{{ $customer->active ? 'مفعّل' : 'متوقف' }}</span></td>
                    @if(auth()->user()->hasPermission('customers.update') || auth()->user()->hasPermission('customers.delete'))
                    <td class="customer-actions-cell" data-label="الإجراءات">
                        <div class="management-actions">
                    @if(auth()->user()->hasPermission('customers.update'))
                        <form method="post" action="{{ route('customers.update', $customer) }}">
                            @csrf
                            @method('put')
                            <input type="hidden" name="active" value="{{ $customer->active ? '0' : '1' }}">
                            <button class="button button-small {{ $customer->active ? 'button-danger' : 'button-primary' }}" type="submit">{{ $customer->active ? 'إيقاف العميل' : 'تفعيل العميل' }}</button>
                        </form>
                        <button class="button button-small" type="button" data-customer-edit-target="customer-edit-dialog-{{ $customer->id }}">تعديل البيانات</button>
                        <dialog class="supplier-dialog" id="customer-edit-dialog-{{ $customer->id }}" aria-labelledby="customer-edit-title-{{ $customer->id }}">
                            <div class="supplier-dialog-heading">
                                <div><h2 id="customer-edit-title-{{ $customer->id }}">تعديل بيانات العميل</h2><p class="muted">{{ $customer->name }}</p></div>
                                <form method="dialog"><button class="button button-quiet supplier-dialog-close" aria-label="إغلاق">×</button></form>
                            </div>
                            <form method="post" action="{{ route('customers.update', $customer) }}" class="stack customer-form">
                                @csrf
                                @method('put')
                                <input type="hidden" name="_customer_form" value="edit">
                                <input type="hidden" name="_customer_id" value="{{ $customer->id }}">
                                <label>اسم العميل<input name="name" value="{{ old('_customer_id') === (string) $customer->id ? old('name', $customer->name) : $customer->name }}" maxlength="255" required></label>
                                <label>رقم التواصل <span class="required">*</span><input name="phone" value="{{ old('_customer_id') === (string) $customer->id ? old('phone', $customer->phone) : $customer->phone }}" maxlength="50" inputmode="tel" required></label>
                                <label>ملاحظات<textarea name="notes" rows="2" maxlength="2000">{{ old('_customer_id') === (string) $customer->id ? old('notes', $customer->notes) : $customer->notes }}</textarea></label>
                                <div class="form-actions">
                                    <button class="button button-primary" type="submit">حفظ البيانات</button>
                                    <button class="button" type="button" data-close-customer-edit>إلغاء</button>
                                </div>
                            </form>
                        </dialog>
                    @endif
                    @if(auth()->user()->hasPermission('customers.delete'))
                        <form method="post" action="{{ route('customers.destroy', $customer) }}" onsubmit="return confirm('هل تريد حذف هذا العميل؟ إذا كان مرتبطًا بطلب سابق فلن يمكن حذفه.');">
                            @csrf
                            @method('delete')
                            <button class="button button-small button-danger" type="submit">حذف العميل</button>
                        </form>
                    @endif
                        </div>
                    </td>
                    @endif
                </tr>
            @empty
                <tr><td colspan="{{ 5 + (auth()->user()->hasPermission('customers.update') || auth()->user()->hasPermission('customers.delete') ? 1 : 0) }}" class="empty-state">لا يوجد عملاء مسجلون بعد.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $customers->links() }}
</section>

<script>
    const customerDialog = document.getElementById('customer-dialog');
    if (customerDialog) {
        document.getElementById('open-customer-dialog').addEventListener('click', () => customerDialog.showModal());
        document.getElementById('cancel-customer-dialog').addEventListener('click', () => customerDialog.close());
        customerDialog.addEventListener('click', (event) => {
            if (event.target === customerDialog) customerDialog.close();
        });
        @if($errors->any() && old('_customer_form') === 'create')
            customerDialog.showModal();
        @endif
    }
    document.querySelectorAll('[data-customer-edit-target]').forEach((button) => {
        const editDialog = document.getElementById(button.dataset.customerEditTarget);
        button.addEventListener('click', () => editDialog.showModal());
        editDialog.querySelector('[data-close-customer-edit]').addEventListener('click', () => editDialog.close());
        editDialog.addEventListener('click', (event) => {
            if (event.target === editDialog) editDialog.close();
        });
        @if($errors->any() && old('_customer_form') === 'edit')
            if (editDialog.querySelector('[name="_customer_id"]').value === @json((string) old('_customer_id'))) {
                editDialog.showModal();
            }
        @endif
    });
</script>
@endsection
