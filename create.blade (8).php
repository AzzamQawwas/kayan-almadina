@extends('layouts.app')

@section('title', 'إعداد حساب المدير')

@section('content')
<section class="login-wrap">
    <div class="card login-card">
        <div class="eyebrow">الإعداد الأول</div>
        <h1>أنشئ حساب المدير</h1>
        <p class="muted">أكمل إعداد النظام بإنشاء حساب مدير خاص بك. لا تظهر هذه الصفحة إلا قبل إعداد أول مدير، وفي بيئة التشغيل المحلية فقط.</p>
        <form method="post" action="{{ route('setup.store') }}" class="stack">
            @csrf
            <label><span class="login-field-title">الاسم <span class="required">*</span></span>
                <input name="name" value="{{ old('name') }}" autocomplete="name" maxlength="255" required autofocus>
            </label>
            <label><span class="login-field-title">البريد الإلكتروني <span class="required">*</span></span>
                <input type="email" name="email" value="{{ old('email') }}" autocomplete="email" maxlength="255" required>
            </label>
            <label><span class="login-field-title">كلمة المرور <span class="required">*</span></span>
                <input type="password" name="password" autocomplete="new-password" minlength="12" required>
                <small class="muted">12 حرفًا على الأقل، مع حرف كبير وحرف صغير ورقم ورمز خاص.</small>
            </label>
            <label><span class="login-field-title">تأكيد كلمة المرور <span class="required">*</span></span>
                <input type="password" name="password_confirmation" autocomplete="new-password" minlength="12" required>
            </label>
            <button class="button button-primary button-block" type="submit">إنشاء حساب المدير</button>
        </form>
    </div>
</section>
@endsection
