@extends('layouts.app')

@section('title', 'التقارير')

@section('content')
<div class="page-heading">
    <div>
        <div class="eyebrow">متابعة الأداء</div>
        <h1>تقارير الطلبات</h1>
        <p class="muted">صورة تشغيلية ومالية لمسار الطلبات ونتائج التوريد والتسليم.</p>
    </div>
</div>

<form method="get" class="filter-bar card report-filter">
    <label class="report-date-field">من تاريخ <input type="date" name="from" value="{{ $from }}"></label>
    <label class="report-date-field">إلى تاريخ <input type="date" name="to" value="{{ $to }}"></label>
    <button class="button button-primary report-filter-submit" type="submit">عرض التقرير</button>
    @if($from || $to)<a class="text-link" href="{{ route('reports.index') }}">كل الفترات</a>@endif
    <small class="report-filter-note">تُحتسب الفترة بحسب تاريخ إنشاء الطلب، وتشمل النتائج المالية الطلبات المغلقة ضمنها.</small>
</form>

<section class="stats-grid report-overview">
    <div class="stat-card"><span>إجمالي الطلبات</span><strong>{{ number_format($totalOrders) }}</strong></div>
    <div class="stat-card"><span>طلبات مغلقة</span><strong>{{ number_format($closedCount) }}</strong></div>
    <div class="stat-card"><span>طلبات قيد التنفيذ</span><strong>{{ number_format($activeCount) }}</strong></div>
    <div class="stat-card"><span>نسبة الإغلاق</span><strong>{{ $totalOrders ? round($closedCount / $totalOrders * 100) : 0 }}<small>%</small></strong></div>
</section>

<section class="card report-section">
    <div class="section-heading">
        <div><h2>الطلبات حسب المرحلة</h2><p class="muted">توزيع جميع الطلبات المنشأة ضمن الفترة المحددة.</p></div>
    </div>
    <div class="report-stage-grid">
        @foreach([
            'purchasing' => ['label' => 'بانتظار المشتريات', 'class' => 'report-stage-purchasing'],
            'awaiting_delivery' => ['label' => 'لدى المندوب للتسليم', 'class' => 'report-stage-delivery'],
            'shortage' => ['label' => 'نقص بانتظار المشتريات', 'class' => 'report-stage-shortage'],
            'closed' => ['label' => 'مغلق', 'class' => 'report-stage-closed'],
        ] as $status => $stage)
            @php
                $stageCount = (int) $statusCounts->get($status, 0);
                $stageRate = $totalOrders ? round($stageCount / $totalOrders * 100) : 0;
            @endphp
            @if($canViewOrders)
                <a class="report-stage {{ $stage['class'] }}" href="{{ route('orders.index', ['status' => $status]) }}">
            @else
                <div class="report-stage {{ $stage['class'] }}">
            @endif
                <span>{{ $stage['label'] }}</span>
                <strong>{{ number_format($stageCount) }}</strong>
                <span class="report-stage-track"><span style="width: {{ $stageRate }}%"></span></span>
                <small>{{ $stageRate }}٪ من الطلبات</small>
            @if($canViewOrders)
                </a>
            @else
                </div>
            @endif
        @endforeach
    </div>
</section>

@php
    $chartLeft = 54;
    $chartRight = 726;
    $chartTop = 28;
    $chartBottom = 204;
    $chartMaximum = max(1, (float) $financialTrend->max('revenue'), (float) $financialTrend->max('cost'));
    $chartStep = $financialTrend->count() > 1 ? ($chartRight - $chartLeft) / ($financialTrend->count() - 1) : 0;
    $chartPoints = [];
    foreach ($financialTrend as $index => $period) {
        $x = $financialTrend->count() > 1 ? $chartLeft + $index * $chartStep : ($chartLeft + $chartRight) / 2;
        $chartPoints[] = [
            'x' => $x,
            'label' => $period['label'],
            'revenueY' => $chartBottom - ($period['revenue'] / $chartMaximum) * ($chartBottom - $chartTop),
            'costY' => $chartBottom - ($period['cost'] / $chartMaximum) * ($chartBottom - $chartTop),
            'profitY' => $chartBottom - (max(0, $period['profit']) / $chartMaximum) * ($chartBottom - $chartTop),
            'revenue' => $period['revenue'],
            'cost' => $period['cost'],
            'profit' => $period['profit'],
        ];
    }
    $revenuePoints = collect($chartPoints)->map(fn ($point) => $point['x'].','.$point['revenueY'])->implode(' ');
    $costPoints = collect($chartPoints)->map(fn ($point) => $point['x'].','.$point['costY'])->implode(' ');
    $profitPoints = collect($chartPoints)->map(fn ($point) => $point['x'].','.$point['profitY'])->implode(' ');
    $chartLabelInterval = max(1, (int) ceil(count($chartPoints) / 8));
    $purchasingRate = $totalOrders ? $statusCounts->get('purchasing', 0) / $totalOrders * 100 : 0;
    $deliveryRate = $totalOrders ? $statusCounts->get('awaiting_delivery', 0) / $totalOrders * 100 : 0;
    $shortageRate = $totalOrders ? $statusCounts->get('shortage', 0) / $totalOrders * 100 : 0;
    $purchasingEnd = $purchasingRate;
    $deliveryEnd = $purchasingEnd + $deliveryRate;
    $shortageEnd = $deliveryEnd + $shortageRate;
@endphp
<section class="report-charts" aria-label="الرسوم البيانية للتقرير">
    <article class="card report-chart-card report-financial-chart">
        <div class="section-heading">
            <div>
                <h2>الاتجاه المالي</h2>
                <p class="muted">
                    {{ ['day' => 'يومي', 'month' => 'شهري', 'quarter' => 'ربع سنوي', 'year' => 'سنوي'][$trendGranularity] }}
                    لإيرادات وتكاليف وأرباح الطلبات المغلقة.
                    @if(!$from && !$to)<span>يعرض الرسم آخر 6 أشهر.</span>@endif
                </p>
            </div>
        </div>
        <div class="report-chart-legend" aria-label="مفتاح الرسم">
            <span><i class="legend-revenue"></i>الإيرادات</span>
            <span><i class="legend-cost"></i>التكاليف</span>
            <span><i class="legend-profit"></i>الربح</span>
        </div>
        @if($closedCount)
            <div class="report-chart-scroll">
                <svg class="report-line-chart" viewBox="0 0 760 260" role="img" aria-label="رسم اتجاه الإيرادات والتكاليف والأرباح">
                    @foreach([0, 1, 2, 3] as $gridIndex)
                        @php($gridY = $chartTop + ($chartBottom - $chartTop) * $gridIndex / 3)
                        <line class="chart-grid-line" x1="{{ $chartLeft }}" y1="{{ $gridY }}" x2="{{ $chartRight }}" y2="{{ $gridY }}" />
                        <text class="chart-axis-label" x="{{ $chartLeft - 8 }}" y="{{ $gridY + 4 }}" text-anchor="end">{{ number_format($chartMaximum * (3 - $gridIndex) / 3, 0) }}</text>
                    @endforeach
                    <polyline class="chart-line chart-line-revenue" pathLength="1" points="{{ $revenuePoints }}" />
                    <polyline class="chart-line chart-line-cost" pathLength="1" points="{{ $costPoints }}" />
                    <polyline class="chart-line chart-line-profit" pathLength="1" points="{{ $profitPoints }}" />
                    @foreach($chartPoints as $index => $point)
                        @foreach(['revenue' => 'الإيرادات', 'cost' => 'التكاليف', 'profit' => 'الربح'] as $series => $seriesLabel)
                            @php($pointY = $series === 'revenue' ? $point['revenueY'] : ($series === 'cost' ? $point['costY'] : $point['profitY']))
                            <circle class="chart-point chart-point-{{ $series }}" cx="{{ $point['x'] }}" cy="{{ $pointY }}" r="3.5">
                                <title>{{ $point['label'] }} — {{ $seriesLabel }}: {{ number_format($point[$series], 2) }} ريال</title>
                            </circle>
                        @endforeach
                        @if($index % $chartLabelInterval === 0 || $index === count($chartPoints) - 1)
                            <text class="chart-axis-label chart-date-label" x="{{ $point['x'] }}" y="232" text-anchor="middle">{{ $point['label'] }}</text>
                        @endif
                    @endforeach
                </svg>
            </div>
        @else
            <div class="report-chart-empty">تظهر الاتجاهات المالية عند إغلاق الطلبات ضمن الفترة المحددة.</div>
        @endif
    </article>

    <article class="card report-chart-card report-status-chart">
        <div class="section-heading">
            <div><h2>توزيع حالات الطلبات</h2><p class="muted">نسبة الطلبات في كل مرحلة من إجمالي الفترة.</p></div>
        </div>
        <div class="report-donut-layout">
            <div
                class="report-donut"
                role="img"
                aria-label="توزيع الطلبات: {{ $totalOrders }} طلب"
                style="background: {{ $totalOrders ? 'conic-gradient(#bd852c 0% '.number_format($purchasingEnd, 3, '.', '').'%, #3977a7 '.number_format($purchasingEnd, 3, '.', '').'% '.number_format($deliveryEnd, 3, '.', '').'%, #b65045 '.number_format($deliveryEnd, 3, '.', '').'% '.number_format($shortageEnd, 3, '.', '').'%, #398269 '.number_format($shortageEnd, 3, '.', '').'% 100%)' : '#e8ebe5' }};"
            >
                <div><strong>{{ number_format($totalOrders) }}</strong><span>طلب</span></div>
            </div>
            <ul class="report-donut-legend">
                <li><i class="legend-purchasing"></i><span>بانتظار المشتريات</span><strong>{{ number_format($statusCounts->get('purchasing', 0)) }}</strong></li>
                <li><i class="legend-delivery"></i><span>لدى المندوب</span><strong>{{ number_format($statusCounts->get('awaiting_delivery', 0)) }}</strong></li>
                <li><i class="legend-shortage"></i><span>نقص قيد المعالجة</span><strong>{{ number_format($statusCounts->get('shortage', 0)) }}</strong></li>
                <li><i class="legend-closed"></i><span>مغلقة</span><strong>{{ number_format($statusCounts->get('closed', 0)) }}</strong></li>
            </ul>
        </div>
    </article>
</section>

<section class="stats-grid stats-grid-finance report-finance">
    <div class="stat-card"><span>إيرادات الطلبات المغلقة</span><strong><x-currency-symbol :amount="$revenue" /></strong></div>
    <div class="stat-card"><span>تكلفة التوريد</span><strong><x-currency-symbol :amount="$cost" /></strong></div>
    <div class="stat-card"><span>صافي الربح</span><strong class="text-positive"><x-currency-symbol :amount="$profit" /></strong></div>
</section>

<section class="stats-grid report-outcomes">
    <div class="stat-card"><span>تسليم كامل</span><strong>{{ number_format($completeDeliveryCount) }}</strong></div>
    <div class="stat-card"><span>نواقص وردت من نفس المورد</span><strong>{{ number_format($sameSupplierCount) }}</strong></div>
    <div class="stat-card"><span>نواقص وُرّدت من مورد بديل</span><strong>{{ number_format($alternateSupplierCount) }}</strong></div>
    <div class="stat-card"><span>نواقص دبّرها العميل</span><strong>{{ number_format($customerShortageCount) }}</strong></div>
</section>

<section class="card report-section">
    <div class="section-heading">
        <div><h2>أداء المندوبين</h2><p class="muted">عدد الطلبات ونسبة إغلاقها وربحية الطلبات المغلقة لكل مندوب.</p></div>
    </div>
    <div class="table-wrap"><table>
        <thead><tr><th>المندوب</th><th>إجمالي الطلبات</th><th>مغلقة</th><th>قيد التنفيذ</th><th>نسبة الإغلاق</th><th>الإيرادات</th><th>الربح</th></tr></thead>
        <tbody>
        @forelse($representativeStats as $representative)
            <tr>
                <td data-label="المندوب">{{ $representative['name'] }}</td>
                <td data-label="إجمالي الطلبات">{{ number_format($representative['total']) }}</td>
                <td data-label="مغلقة">{{ number_format($representative['closed']) }}</td>
                <td data-label="قيد التنفيذ">{{ number_format($representative['active']) }}</td>
                <td data-label="نسبة الإغلاق">{{ $representative['completion_rate'] }}٪</td>
                <td data-label="الإيرادات"><x-currency-symbol :amount="$representative['revenue']" /></td>
                <td data-label="الربح" class="text-positive"><x-currency-symbol :amount="$representative['profit']" /></td>
            </tr>
        @empty
            <tr><td colspan="7" class="empty-state">لا توجد طلبات ضمن الفترة المحددة.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</section>

<section class="card report-section">
    <div class="section-heading">
        <div><h2>أداء الموردين</h2><p class="muted">التوريدات المغلقة موزعة على المورد الأساسي والمورد البديل عند معالجة النواقص.</p></div>
    </div>
    <div class="table-wrap"><table>
        <thead><tr><th>المورد</th><th>عدد الطلبات الموردة</th><th>قيمة المبيعات المرتبطة بالتوريد</th><th>تكلفة التوريد</th><th>الربح</th></tr></thead>
        <tbody>
        @forelse($supplierStats as $supplier)
            <tr>
                <td data-label="المورد">{{ $supplier['name'] }}</td>
                <td data-label="عدد الطلبات الموردة">{{ number_format($supplier['orders']) }}</td>
                <td data-label="قيمة المبيعات المرتبطة بالتوريد"><x-currency-symbol :amount="$supplier['revenue']" /></td>
                <td data-label="تكلفة التوريد"><x-currency-symbol :amount="$supplier['cost']" /></td>
                <td data-label="الربح" class="text-positive"><x-currency-symbol :amount="$supplier['profit']" /></td>
            </tr>
        @empty
            <tr><td colspan="5" class="empty-state">لا توجد توريدات مغلقة ضمن الفترة المحددة.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</section>

<section class="card report-section">
    <div class="section-heading">
        <div><h2>تفاصيل الطلبات المغلقة</h2><p class="muted">الإيراد والتكلفة والربح الفعلي لكل طلب مغلق ضمن الفترة.</p></div>
    </div>
    <div class="table-wrap"><table>
        <thead><tr><th>رقم الطلب</th><th>العميل</th><th>المندوب</th><th>الإغلاق</th><th>الإيراد</th><th>التكلفة</th><th>الربح</th></tr></thead>
        <tbody>@forelse($orders as $order)
            <tr>
                <td data-label="رقم الطلب">
                    @if($canViewOrders)
                        <a class="text-link" href="{{ route('orders.show', $order) }}">{{ $order->order_number }}</a>
                    @else
                        {{ $order->order_number }}
                    @endif
                </td>
                <td data-label="العميل">{{ $order->customer_name }}</td>
                <td data-label="المندوب">{{ $order->representative->name }}</td>
                <td data-label="تاريخ الإغلاق">{{ $order->closed_at?->format('Y-m-d') }}</td>
                <td data-label="الإيراد"><x-currency-symbol :amount="$order->revenue" /></td>
                <td data-label="التكلفة"><x-currency-symbol :amount="$order->cost" /></td>
                <td data-label="الربح" class="text-positive"><x-currency-symbol :amount="$order->profit" /></td>
            </tr>
        @empty
            <tr><td colspan="7" class="empty-state">لا توجد طلبات مغلقة ضمن الفترة المحددة.</td></tr>
        @endforelse</tbody>
    </table></div>
    <div class="pagination">{{ $orders->links() }}</div>
</section>
@endsection
