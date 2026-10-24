@extends('layouts.app')

@section('title', 'الطلبات')

@section('content')
<div class="page-heading">
    <div><div class="eyebrow">سير العمل</div><h1>الطلبات</h1><p class="muted">طلبات العملاء ومراحل توريدها وتسليمها.</p></div>
    @if(auth()->user()->hasPermission('orders.create'))
        <a class="button button-primary" href="{{ route('orders.create') }}">+ طلب جديد</a>
    @endif
</div>
<form method="get" class="filter-bar card">
    <label for="status">تصفية حسب الحالة</label>
    <select name="status" id="status">
        <option value="">كل الحالات</option>
        @foreach(['purchasing' => 'بانتظار المشتريات', 'awaiting_delivery' => 'لدى المندوب للتسليم', 'shortage' => 'نقص — بانتظار المشتريات', 'closed' => 'مغلق'] as $value => $label)
            <option value="{{ $value }}" @selected($selectedStatus === $value)>{{ $label }}</option>
        @endforeach
    </select>
    <button class="button" type="submit">تطبيق</button>
    @if($selectedStatus)<a class="text-link" href="{{ route('orders.index') }}">مسح التصفية</a>@endif
</form>
<section class="card">
    @include('orders._table', ['orders' => $orders])
    <div class="pagination">{{ $orders->links() }}</div>
</section>
@endsection
