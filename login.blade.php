@extends('layouts.app')

@section('title', 'تسجيل الدخول')

@section('content')
<section class="login-wrap">
    <div class="card login-card">
        <div class="login-brand">
            <img src="{{ asset('images/kayan-logo.png') }}" alt="كيان المدينة" class="login-logo">
            <span>نظام إدارة المؤسسة</span>
        </div>
        <div class="login-intro">
            <div class="login-badge"><span aria-hidden="true"></span> بوابة العمل الآمنة</div>
            <h1>مرحبًا بك في كيان المدينة</h1>
            <p>سجّل الدخول بحسابك للوصول إلى مهامك والأقسام المسموح لك بها.</p>
        </div>
        @if(!empty($setupRequired))
            <div class="setup-required-note" role="status">لم يُنشأ حساب المدير بعد. أكمل إعداد قاعدة البيانات، ثم أنشئ أول مدير من الطرفية باستخدام الأمر <code>php artisan app:create-admin</code>.</div>
        @endif
        <form method="post" action="{{ route('login.store') }}" class="stack login-form">
            @csrf
            <label>
                <span>البريد الإلكتروني <span class="required">*</span></span>
                <input type="email" name="email" value="{{ old('email') }}" autocomplete="username" required autofocus placeholder="name@example.com" dir="ltr">
            </label>
            <label>
                <span>كلمة المرور <span class="required">*</span></span>
                <span class="login-password-field">
                    <input id="login-password" type="password" name="password" autocomplete="current-password" required placeholder="أدخل كلمة المرور">
                    <button class="login-password-toggle" type="button" aria-label="إظهار كلمة المرور" aria-pressed="false">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.2-6.2 9.5-6.2 9.5 6.2 9.5 6.2-3.2 6.2-9.5 6.2S2.5 12 2.5 12Z"/><circle cx="12" cy="12" r="2.7"/></svg>
                    </button>
                </span>
            </label>
            <label class="check-row login-remember"><input type="checkbox" name="remember" value="1"> تذكرني على هذا الجهاز</label>
            <button class="button button-primary button-block login-submit" type="submit">تسجيل الدخول <span aria-hidden="true">←</span></button>
        </form>
        <p class="login-secure-note"><span aria-hidden="true">●</span> بيانات الدخول محمية وتُستخدم للوصول المصرح به فقط.</p>
    </div>
</section>
<script>
    const passwordInput = document.getElementById('login-password');
    const passwordToggle = document.querySelector('.login-password-toggle');
    passwordToggle.addEventListener('click', () => {
        const showPassword = passwordInput.type === 'password';
        passwordInput.type = showPassword ? 'text' : 'password';
        passwordToggle.setAttribute('aria-pressed', String(showPassword));
        passwordToggle.setAttribute('aria-label', showPassword ? 'إخفاء كلمة المرور' : 'إظهار كلمة المرور');
    });
</script>
@endsection
