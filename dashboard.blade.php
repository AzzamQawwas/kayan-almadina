@extends('layouts.app')

@section('title', 'لوحة التحكم')

@section('content')
@php
    $canViewOrders = auth()->user()->hasPermission('orders.view');
    $canViewReports = auth()->user()->hasPermission('reports.view');
    $canViewSuppliers = auth()->user()->hasPermission('suppliers.view');
    $canPurchase = auth()->user()->hasAnyPermission(['orders.purchase', 'orders.resolve_shortage']);
    $canCreateOrders = auth()->user()->hasPermission('orders.create');
    $purchaseStatus = auth()->user()->hasPermission('orders.purchase') ? 'purchasing' : 'shortage';
@endphp

<div class="dashboard-heading">
    <div>
        <span class="dashboard-eyebrow">نظرة عامة على سير العمل</span>
        <h1>لوحة إدارة التوريد</h1>
    </div>
</div>

<section class="dashboard-hero" aria-labelledby="dashboard-welcome">
    <div class="hero-copy">
        <span class="hero-eyebrow">توريد أوضح، متابعة أسهل</span>
        <h2 id="dashboard-welcome">أهلًا {{ auth()->user()->name }}، <span>طلبات اليوم جاهزة للمتابعة.</span></h2>
        <p>تابع طلبات مواد البناء من تسجيل المندوب، مرورًا بتأكيد المورد، حتى التسليم وإغلاق الطلب.</p>
        <div class="hero-actions">
            @if($canCreateOrders)
                <a class="button button-gold" href="{{ route('orders.create') }}">إنشاء طلب جديد <span aria-hidden="true">←</span></a>
            @endif
            @if($canPurchase)
                <a class="button button-hero-quiet" href="{{ route('orders.index', ['status' => $purchaseStatus]) }}">{{ $purchaseStatus === 'purchasing' ? 'طلبات بانتظار الشراء' : 'معالجة النواقص' }} <span aria-hidden="true">←</span></a>
            @elseif($canViewOrders)
                <a class="button button-hero-quiet" href="{{ route('orders.index') }}">متابعة الطلبات <span aria-hidden="true">←</span></a>
            @endif
        </div>
    </div>
</section>

<section class="dashboard-shortcuts" aria-label="اختصارات العمل">
    @if($canViewOrders)
        <a class="shortcut-card" href="{{ route('orders.index') }}">
            <span class="shortcut-icon" aria-hidden="true">▤</span>
            <span class="shortcut-copy"><strong>طلبات العملاء</strong><small>إنشاء الطلبات ومتابعة التسليم والنواقص</small></span>
            <span class="shortcut-arrow" aria-hidden="true">←</span>
        </a>
    @endif
    @if($canPurchase)
        <a class="shortcut-card" href="{{ route('orders.index', ['status' => $purchaseStatus]) }}">
            <span class="shortcut-icon shortcut-icon-gold" aria-hidden="true">⇄</span>
            <span class="shortcut-copy"><strong>قسم المشتريات</strong><small>تأكيد المورد والأسعار ومعالجة النواقص</small></span>
            <span class="shortcut-arrow" aria-hidden="true">←</span>
        </a>
    @endif
</section>

@if($canViewOrders)
<div class="dashboard-section-heading">
    <div>
        <h2>ملخص الطلبات</h2>
        <p>قيمة كل بطاقة تفتح الطلبات في مرحلة العمل المقابلة.</p>
    </div>
    <a class="text-link" href="{{ route('orders.index') }}">عرض كل الطلبات <span aria-hidden="true">←</span></a>
</div>

<section class="dashboard-stats" aria-label="ملخص الطلبات">
    <a class="dashboard-stat stat-purchasing" href="{{ route('orders.index', ['status' => 'purchasing']) }}">
        <span class="dashboard-stat-top"><span class="dashboard-stat-icon" aria-hidden="true">⇄</span><span class="dashboard-stat-label">بانتظار المشتريات</span></span>
        <strong>{{ $counts['purchasing'] ?? 0 }}</strong>
        <small>طلبات بانتظار تأكيد المورد والسعر</small>
    </a>
    <a class="dashboard-stat stat-delivery" href="{{ route('orders.index', ['status' => 'awaiting_delivery']) }}">
        <span class="dashboard-stat-top"><span class="dashboard-stat-icon" aria-hidden="true">➜</span><span class="dashboard-stat-label">لدى المندوب للتسليم</span></span>
        <strong>{{ $counts['awaiting_delivery'] ?? 0 }}</strong>
        <small>طلبات جاهزة لتأكيد الاستلام</small>
    </a>
    <a class="dashboard-stat stat-shortage" href="{{ route('orders.index', ['status' => 'shortage']) }}">
        <span class="dashboard-stat-top"><span class="dashboard-stat-icon" aria-hidden="true">!</span><span class="dashboard-stat-label">نواقص بانتظار المعالجة</span></span>
        <strong>{{ $counts['shortage'] ?? 0 }}</strong>
        <small>طلبات تحتاج إجراء من المشتريات</small>
    </a>
    <a class="dashboard-stat stat-closed" href="{{ route('orders.index', ['status' => 'closed']) }}">
        <span class="dashboard-stat-top"><span class="dashboard-stat-icon" aria-hidden="true">✓</span><span class="dashboard-stat-label">طلبات مغلقة</span></span>
        <strong>{{ $counts['closed'] ?? 0 }}</strong>
        <small>طلبات اكتمل تسليمها</small>
    </a>
</section>
@endif

@if($canViewReports)
    <section class="dashboard-finance" aria-label="ملخص مالي للطلبات المغلقة">
        <div class="finance-card">
            <span class="finance-card-icon" aria-hidden="true">ر</span>
            <span><small>إيراد الطلبات المغلقة</small><strong><x-currency-symbol :amount="$closedRevenue" /></strong></span>
        </div>
        <div class="finance-card finance-profit">
            <span class="finance-card-icon" aria-hidden="true">↗</span>
            <span><small>ربح الطلبات المغلقة</small><strong><x-currency-symbol :amount="$closedProfit" /></strong></span>
        </div>
        <a class="finance-report-link" href="{{ route('reports.index') }}">تفاصيل التقارير والأرباح <span aria-hidden="true">←</span></a>
    </section>
@endif

    @if($canViewOrders)
    <section class="card dashboard-orders-card">
    <div class="section-heading">
        <div><h2>أحدث الطلبات</h2><p class="muted">آخر الطلبات التي تحتاج إلى متابعة.</p></div>
        <a class="text-link" href="{{ route('orders.index') }}">عرض القائمة كاملة <span aria-hidden="true">←</span></a>
    </div>
    @include('orders._table', ['orders' => $orders])
</section>
@endif

@if($canViewSuppliers)
    <section class="card dashboard-suppliers-card">
        <div class="section-heading">
            <div><h2>الموردون</h2><p class="muted">حالة المورد وعدد الطلبات التي تم التوريد منه فيها.</p></div>
            <a class="text-link" href="{{ route('suppliers.index') }}">إدارة الموردين <span aria-hidden="true">←</span></a>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>المورد</th><th>الحالة</th><th>عدد الطلبات الموردة</th></tr></thead>
                <tbody>
                @forelse($suppliers as $supplier)
                    <tr>
                        <td data-label="المورد"><strong>{{ $supplier->name }}</strong></td>
                        <td data-label="الحالة"><span class="status {{ $supplier->active ? 'status-closed' : 'status-shortage' }}">{{ $supplier->active ? 'مفعّل' : 'متوقف' }}</span></td>
                        <td data-label="عدد الطلبات الموردة">{{ $supplier->supplied_orders_count }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="empty-state">لا يوجد موردون مسجلون بعد.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endif
@endsection
