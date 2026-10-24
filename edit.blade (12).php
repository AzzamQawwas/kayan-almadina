@extends('layouts.app')

@section('title', 'تعديل المستخدم '.$user->name)

@section('content')
<div class="page-heading">
    <div>
        <div class="eyebrow">إدارة الوصول</div>
        <h1>تعديل بيانات المستخدم</h1>
        <p class="muted">{{ $user->name }} — {{ $user->roleLabel() }}. تغيير الدور أو حالة الحساب من إدارة الوصول في قائمة المستخدمين.</p>
    </div>
    <a class="button" href="{{ route('users.index') }}">العودة للمستخدمين</a>
</div>

<form method="post" action="{{ route('users.update', $user) }}" class="card stack">
    @csrf
    @method('put')
    <div class="form-grid">
        <label>الاسم <span class="required">*</span>
            <input name="name" value="{{ old('name', $user->name) }}" maxlength="255" required>
        </label>
        <label>البريد الإلكتروني <span class="required">*</span>
            <input type="email" name="email" value="{{ old('email', $user->email) }}" maxlength="255" required>
        </label>
        <label>كلمة مرور جديدة
            <input type="password" name="password" autocomplete="new-password" minlength="12">
            <small class="muted">اتركها فارغة للإبقاء على كلمة المرور الحالية. وإذا غيّرتها، يجب أن تتكون من 12 حرفًا على الأقل وتضم حرفًا كبيرًا وحرفًا صغيرًا ورقمًا ورمزًا خاصًا.</small>
        </label>
        <label>تأكيد كلمة المرور الجديدة
            <input type="password" name="password_confirmation" autocomplete="new-password" minlength="12">
        </label>
    </div>
    <div class="form-actions">
        <button class="button button-primary" type="submit">حفظ التعديلات</button>
        <a class="button" href="{{ route('users.index') }}">إلغاء</a>
    </div>
</form>
@endsection
