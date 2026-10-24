<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'نظام التوريد') — {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
</head>
<body>
    @auth
        <header class="dashboard-topbar">
            <a class="dashboard-brand" href="{{ route('dashboard') }}">
                <img class="dashboard-brand-logo" src="{{ asset('images/kayan-logo.png') }}" alt="كيان المدينة — Kayan AlMadinah">
            </a>
            <details class="dashboard-account-menu">
                <summary class="dashboard-account">
                    @if(auth()->user()->avatar_path)
                        <img class="dashboard-avatar dashboard-avatar-image" src="{{ route('profile.avatar') }}" alt="">
                    @else
                        <span class="dashboard-avatar" aria-hidden="true">{{ mb_substr(auth()->user()->name, 0, 1) }}</span>
                    @endif
                    <span class="dashboard-account-name">{{ auth()->user()->name }}<small>{{ auth()->user()->roleLabel() }}</small></span>
                    <span class="dashboard-account-chevron" aria-hidden="true"></span>
                </summary>
                <div class="dashboard-account-menu-panel">
                    <a href="{{ route('profile.edit') }}">تعديل بيانات المستخدم</a>
                    <form method="post" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit">تسجيل الخروج</button>
                    </form>
                </div>
            </details>
        </header>
        <div class="dashboard-layout">
            <aside class="dashboard-sidebar" aria-label="التنقل الرئيسي">
                <div class="sidebar-section">
                    <span class="sidebar-heading">مساحة العمل</span>
                    <a class="sidebar-link {{ request()->routeIs('dashboard') ? 'is-active' : '' }}" href="{{ route('dashboard') }}">
                        <span class="sidebar-icon" aria-hidden="true">⌂</span>
                        <span>الرئيسية</span>
                    </a>
                    @if(auth()->user()->hasPermission('orders.view'))
                        <a class="sidebar-link {{ request()->routeIs('orders.index', 'orders.show') ? 'is-active' : '' }}" href="{{ route('orders.index') }}">
                            <span class="sidebar-icon" aria-hidden="true">▤</span>
                            <span>طلبات العملاء</span>
                        </a>
                    @endif
                    @if(auth()->user()->hasPermission('orders.create'))
                        <a class="sidebar-link sidebar-sub-link {{ request()->routeIs('orders.create') ? 'is-active' : '' }}" href="{{ route('orders.create') }}">
                            <span class="sidebar-icon" aria-hidden="true">＋</span>
                            <span>إنشاء طلب جديد</span>
                        </a>
                    @endif
                    @if(auth()->user()->hasPermission('customers.view'))
                        <a class="sidebar-link {{ request()->routeIs('customers.*') ? 'is-active' : '' }}" href="{{ route('customers.index') }}">
                            <span class="sidebar-icon" aria-hidden="true">♙</span>
                            <span>إدارة العملاء</span>
                        </a>
                    @endif
                </div>

                @if(auth()->user()->hasAnyPermission(['orders.purchase', 'orders.resolve_shortage', 'suppliers.view']))
                    <div class="sidebar-section">
                        <span class="sidebar-heading">التوريد</span>
                        @if(auth()->user()->hasAnyPermission(['orders.purchase', 'orders.resolve_shortage']))
                        <details class="sidebar-purchases" id="nav-purchases-menu">
                            <summary class="sidebar-link">
                                <span class="sidebar-icon" aria-hidden="true">⇄</span>
                                <span>قسم المشتريات</span>
                                <span class="sidebar-chevron" aria-hidden="true"></span>
                            </summary>
                            <div class="sidebar-submenu">
                                @if(auth()->user()->hasPermission('orders.purchase'))
                                <a href="{{ route('orders.index', ['status' => 'purchasing']) }}">
                                    <span class="sidebar-dot" aria-hidden="true"></span>
                                    طلبات بانتظار الشراء
                                </a>
                                @endif
                                @if(auth()->user()->hasPermission('orders.resolve_shortage'))
                                <a href="{{ route('orders.index', ['status' => 'shortage']) }}">
                                    <span class="sidebar-dot" aria-hidden="true"></span>
                                    معالجة النواقص
                                </a>
                                @endif
                            </div>
                        </details>
                        @endif
                        @if(auth()->user()->hasPermission('suppliers.view'))
                        <a class="sidebar-link {{ request()->routeIs('suppliers.*') ? 'is-active' : '' }}" href="{{ route('suppliers.index') }}">
                            <span class="sidebar-icon" aria-hidden="true">♧</span>
                            <span>إدارة الموردين</span>
                        </a>
                        @endif
                    </div>
                @endif

                @if(auth()->user()->hasPermission('reports.view'))
                    <div class="sidebar-section">
                        <span class="sidebar-heading">متابعة الأداء</span>
                        <a class="sidebar-link {{ request()->routeIs('reports.*') ? 'is-active' : '' }}" href="{{ route('reports.index') }}">
                            <span class="sidebar-icon" aria-hidden="true">▥</span>
                            <span>التقارير والأرباح</span>
                        </a>
                    </div>
                @endif

                @if(auth()->user()->hasAnyPermission(['users.view', 'users.permissions', 'units.view']))
                    <div class="sidebar-section">
                        <span class="sidebar-heading">إدارة النظام</span>
                        @if(auth()->user()->hasAnyPermission(['users.view', 'users.permissions']))
                        <a class="sidebar-link {{ request()->routeIs('users.*') ? 'is-active' : '' }}" href="{{ route('users.index') }}">
                            <span class="sidebar-icon" aria-hidden="true">♙</span>
                            <span>المستخدمون والصلاحيات</span>
                        </a>
                        @endif
                        @if(auth()->user()->hasPermission('units.view'))
                        <a class="sidebar-link {{ request()->routeIs('units.*') ? 'is-active' : '' }}" href="{{ route('units.index') }}">
                            <span class="sidebar-icon" aria-hidden="true">≡</span>
                            <span>وحدات المنتجات</span>
                        </a>
                        @endif
                    </div>
                @endif

            </aside>
            <main class="container dashboard-main {{ request()->routeIs('dashboard') ? 'dashboard-home-main' : 'workspace-main' }}">
                @if(session('success'))
                    <div class="notice notice-success" role="status">{{ session('success') }}</div>
                @endif
                @if($errors->any())
                    <div class="notice notice-error" role="alert">
                        <strong>تعذر حفظ البيانات:</strong>
                        <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </div>
                @endif
                @yield('content')
            </main>
        </div>
    @else
        <header class="guest-topbar">
            <a class="dashboard-brand" href="{{ route('login') }}">
                <img class="dashboard-brand-logo" src="{{ asset('images/kayan-logo.png') }}" alt="كيان المدينة — Kayan AlMadinah">
            </a>
            <span class="guest-topbar-note">بوابة العمل الآمنة</span>
        </header>
        <main class="container guest-main">
            @if(session('success'))
                <div class="notice notice-success" role="status">{{ session('success') }}</div>
            @endif
            @if($errors->any())
                <div class="notice notice-error" role="alert">
                    <strong>تعذر حفظ البيانات:</strong>
                    <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif
            @yield('content')
        </main>
    @endif
    @if(auth()->check() && auth()->user()->hasPermission('orders.purchase'))
    <script>
    window.addEventListener('pageshow', function () {
        const purchasesMenu = document.getElementById('nav-purchases-menu');
        if (purchasesMenu) {
            purchasesMenu.removeAttribute('open');
        }
    });
    </script>
    @endif
</body>
</html>
