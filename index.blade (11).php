@extends('layouts.app')

@section('title', 'وحدات المنتجات')

@section('content')
<div class="page-heading">
    <div>
        <div class="eyebrow">إدارة النظام</div>
        <h1>وحدات المنتجات</h1>
        <p class="muted">أضف وحدات مواد البناء، ثم اختر منها عند إنشاء الطلب. إيقاف الوحدة لا يغيّر الطلبات السابقة.</p>
    </div>
</div>

@if(auth()->user()->hasPermission('units.create'))
<section class="card unit-create-card">
    <div class="section-heading">
        <div><h2>إضافة وحدة</h2><p class="muted">تظهر الوحدة المفعّلة مباشرة في قائمة وحدات الطلب.</p></div>
    </div>
    <form method="post" action="{{ route('units.store') }}" class="unit-create-form">
        @csrf
        <label for="unit-name">اسم الوحدة <span class="required">*</span>
            <input id="unit-name" name="name" value="{{ old('name') }}" maxlength="50" placeholder="مثال: ربطة" required>
        </label>
        <button class="button button-primary" type="submit">إضافة الوحدة</button>
    </form>
</section>
@endif

<section class="card unit-list-card">
    <div class="section-heading">
        <div><h2>الوحدات المسجلة</h2><p class="muted">{{ $units->where('active', true)->count() }} وحدة مفعّلة للاستخدام في الطلبات الجديدة.</p></div>
    </div>
    <div class="table-wrap management-table-wrap unit-table-wrap">
        <table>
            <thead><tr><th>الوحدة</th><th>الحالة</th>@if(auth()->user()->hasPermission('units.update'))<th>إدارة</th>@endif @if(auth()->user()->hasPermission('units.delete'))<th>حذف</th>@endif</tr></thead>
            <tbody>
            @forelse($units as $unit)
                <tr class="management-table-row unit-table-row">
                    <td data-label="الوحدة"><strong>{{ $unit->name }}</strong></td>
                    <td data-label="الحالة">
                        <span class="status {{ $unit->active ? 'status-closed' : 'status-shortage' }}">{{ $unit->active ? 'مفعّلة' : 'متوقفة' }}</span>
                    </td>
                    @if(auth()->user()->hasPermission('units.update'))
                    <td data-label="إدارة">
                        <form method="post" action="{{ route('units.update', $unit) }}">
                            @csrf
                            @method('put')
                            <input type="hidden" name="active" value="{{ $unit->active ? '0' : '1' }}">
                            <button class="button button-small {{ $unit->active ? 'button-danger' : 'button-primary' }}" type="submit">
                                {{ $unit->active ? 'إيقاف الاستخدام' : 'تفعيل الوحدة' }}
                            </button>
                        </form>
                    </td>
                    @endif
                    @if(auth()->user()->hasPermission('units.delete'))
                    <td data-label="حذف">
                        <form method="post" action="{{ route('units.destroy', $unit) }}" onsubmit="return confirm('هل تريد حذف هذه الوحدة؟ إذا كانت مستخدمة في طلب سابق فلن يمكن حذفها.');">
                            @csrf
                            @method('delete')
                            <button class="button button-small button-danger unit-delete-button" type="submit">حذف الوحدة</button>
                        </form>
                    </td>
                    @endif
                </tr>
            @empty
                <tr><td colspan="{{ 2 + (auth()->user()->hasPermission('units.update') ? 1 : 0) + (auth()->user()->hasPermission('units.delete') ? 1 : 0) }}" class="empty-state">لا توجد وحدات مسجلة بعد.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
