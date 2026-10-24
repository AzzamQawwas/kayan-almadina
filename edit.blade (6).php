@extends('layouts.app')

@section('title', 'بياناتي')

@section('content')
<div class="page-heading">
    <div>
        <div class="eyebrow">الحساب الشخصي</div>
        <h1>بياناتي</h1>
        <p class="muted">حدّث بياناتك وصورة حسابك. تغيير كلمة المرور يتطلب إدخال كلمة المرور الحالية.</p>
    </div>
    <a class="button" href="{{ route('dashboard') }}">العودة للرئيسية</a>
</div>

<form method="post" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="card stack profile-form">
    @csrf
    @method('put')
    <div class="profile-form-header">
        <div class="profile-preview">
            @if($user->avatar_path)
                <img class="profile-avatar-image" src="{{ route('profile.avatar') }}" alt="صورة {{ $user->name }}">
            @else
                <span class="profile-avatar-fallback">{{ mb_substr($user->name, 0, 1) }}</span>
            @endif
        </div>
        <div>
            <h2>{{ $user->name }}</h2>
            <p class="muted">{{ $user->roleLabel() }}</p>
        </div>
    </div>

    <div class="form-grid">
        <label>الاسم <span class="required">*</span>
            <input name="name" value="{{ old('name', $user->name) }}" maxlength="255" required>
        </label>
        <label>البريد الإلكتروني <span class="required">*</span>
            <input type="email" name="email" value="{{ old('email', $user->email) }}" maxlength="255" required>
        </label>
        <label>صورة الحساب
            <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp">
            <small class="muted">JPEG أو PNG أو WebP، بحد أقصى 5 ميجابايت.</small>
        </label>
        @if($user->avatar_path)
            <label class="check-row profile-remove-avatar">
                <input type="checkbox" name="remove_avatar" value="1" @checked(old('remove_avatar'))>
                <span>إزالة صورة الحساب الحالية</span>
            </label>
        @endif
    </div>

    <div class="profile-password-section">
        <div class="section-heading">
            <div>
                <h2>تغيير كلمة المرور</h2>
                <p class="muted">اترك الحقول فارغة إذا لم ترد تغييرها.</p>
            </div>
        </div>
        <div class="form-grid">
            <label>كلمة المرور الحالية
                <input type="password" name="current_password" autocomplete="current-password">
            </label>
            <label>كلمة المرور الجديدة
                <input type="password" name="password" autocomplete="new-password" minlength="12">
                <small class="muted">12 حرفًا على الأقل، وتضم حرفًا كبيرًا وحرفًا صغيرًا ورقمًا ورمزًا خاصًا.</small>
            </label>
            <label>تكرار كلمة المرور الجديدة
                <input type="password" name="password_confirmation" autocomplete="new-password" minlength="12">
            </label>
        </div>
    </div>

    <div class="form-actions">
        <button class="button button-primary" type="submit">حفظ بياناتي</button>
        <a class="button" href="{{ route('dashboard') }}">إلغاء</a>
    </div>
</form>
@endsection
