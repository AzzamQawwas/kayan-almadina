@extends('layouts.app')

@section('title', 'صلاحيات '.$user->name)

@section('content')
@php
    $selectedPermissions = session()->hasOldInput('permissions_present')
        ? old('permissions', [])
        : $grantedPermissions;
@endphp
<div class="page-heading">
    <div>
        <div class="eyebrow">إدارة الوصول</div>
        <h1>صلاحيات المستخدم</h1>
        <p class="muted">{{ $user->name }} — {{ $user->roleLabel() }}. يحدد الدور الصلاحيات الافتراضية فقط؛ يمكنك منح هذا المستخدم أي صلاحية من القائمة، بشرط أن تكون متاحة لك. تتطلب إجراءات الصفحات صلاحية عرض الصفحة ذات الصلة.</p>
    </div>
    <a class="button" href="{{ route('users.index') }}">العودة للمستخدمين</a>
</div>

<form method="post" action="{{ route('users.permissions.update', $user) }}" class="card permissions-form">
    @csrf
    @method('put')
    <input type="hidden" name="permissions_present" value="1">
    @foreach($permissionGroups as $group => $permissions)
        <fieldset class="permission-group">
            <legend>{{ $group }}</legend>
            <div class="permission-options">
                @foreach($permissions as $permission)
                    <label class="permission-option">
                        <input type="checkbox" name="permissions[]" value="{{ $permission['key'] }}" @checked(is_array($selectedPermissions) && in_array($permission['key'], $selectedPermissions, true))>
                        <span>{{ $permission['label'] }}</span>
                    </label>
                @endforeach
            </div>
        </fieldset>
    @endforeach
    <div class="form-actions">
        <button class="button button-primary" type="submit">حفظ الصلاحيات</button>
        <a class="button" href="{{ route('users.index') }}">إلغاء</a>
    </div>
</form>
@endsection
