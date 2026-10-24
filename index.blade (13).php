@extends('layouts.app')

@section('title', 'إدارة المستخدمين')

@section('content')
<div class="page-heading management-page-heading">
    <div><div class="eyebrow">إدارة الوصول</div><h1>المستخدمون</h1><p class="muted">أنشئ حسابات المندوبين وفريق المشتريات ومديري النظام.</p></div>
    @if(auth()->user()->hasPermission('users.create'))
        <button class="button button-primary" type="button" id="open-user-dialog">＋ إضافة مستخدم</button>
    @endif
</div>

@if(auth()->user()->hasPermission('users.create'))
<dialog class="management-dialog" id="user-dialog" aria-labelledby="user-dialog-title">
    <div class="management-dialog-heading">
        <div><h2 id="user-dialog-title">إضافة مستخدم</h2><p class="muted">أدخل بيانات الحساب وحدد صلاحياته في النظام.</p></div>
        <form method="dialog"><button class="button button-quiet management-dialog-close" aria-label="إغلاق">×</button></form>
    </div>
    <form method="post" action="{{ route('users.store') }}" class="form-grid management-create-form">
        @csrf
        <label>الاسم <span class="required">*</span><input name="name" value="{{ old('name') }}" maxlength="255" required></label>
        <label>البريد الإلكتروني <span class="required">*</span><input type="email" name="email" value="{{ old('email') }}" required></label>
        <label>الصلاحية <span class="required">*</span>
            <select name="role" required>
                <option value="">اختر الدور</option>
                <option value="representative" @selected(old('role') === 'representative')>مندوب</option>
                <option value="purchasing" @selected(old('role') === 'purchasing')>المشتريات</option>
                <option value="admin" @selected(old('role') === 'admin')>مدير النظام</option>
            </select>
        </label>
        <label>كلمة المرور <span class="required">*</span><input type="password" name="password" autocomplete="new-password" minlength="12" required><small class="muted">12 حرفًا على الأقل، مع حرف كبير وحرف صغير ورقم ورمز خاص.</small></label>
        <label>تأكيد كلمة المرور <span class="required">*</span><input type="password" name="password_confirmation" autocomplete="new-password" required></label>
        <div class="field-wide form-actions">
            <button class="button button-primary" type="submit">إنشاء الحساب</button>
            <button class="button" type="button" id="cancel-user-dialog">إلغاء</button>
        </div>
    </form>
</dialog>
@endif

<section class="card management-users-card">
    <h2>الحسابات الحالية</h2>
    <div class="table-wrap"><table>
        <thead><tr><th>الاسم</th><th>البريد الإلكتروني</th><th>الدور</th><th>الحالة</th><th>تاريخ الإضافة</th>@if(auth()->user()->hasPermission('users.update'))<th>تعديل البيانات</th>@endif<th>الصلاحيات</th>@if(auth()->user()->hasPermission('users.manage'))<th>إدارة الوصول</th>@endif</tr></thead>
        <tbody>@foreach($users as $user)
            <tr class="management-table-row user-table-row"><td data-label="الاسم">{{ $user->name }}</td><td data-label="البريد الإلكتروني">{{ $user->email }}</td>
                <td data-label="الدور">{{ $user->roleLabel() }}</td>
                <td data-label="الحالة"><span class="status {{ $user->active ? 'status-closed' : 'status-shortage' }}">{{ $user->active ? 'مفعّل' : 'غير مفعّل' }}</span></td>
                <td data-label="تاريخ الإضافة">{{ $user->created_at->format('Y-m-d') }}</td>
                @if(auth()->user()->hasPermission('users.update'))
                <td data-label="تعديل البيانات">
                    <a class="button button-small" href="{{ route('users.edit', $user) }}">تعديل البيانات</a>
                </td>
                @endif
                <td data-label="الصلاحيات">
                    @if(auth()->user()->hasPermission('users.permissions'))
                        <a class="button button-small" href="{{ route('users.permissions.edit', $user) }}">إدارة الصلاحيات</a>
                    @else
                        <span class="muted">—</span>
                    @endif
                </td>
                @if(auth()->user()->hasPermission('users.manage'))
                <td data-label="إدارة الوصول">
                    @if($user->is(auth()->user()))
                        <span class="muted">الحساب الحالي</span>
                    @else
                        <form method="post" action="{{ route('users.access', $user) }}" class="access-form">
                            @csrf
                            @method('put')
                            <select name="role" aria-label="دور {{ $user->name }}">
                                <option value="representative" @selected($user->role === 'representative')>مندوب</option>
                                <option value="purchasing" @selected($user->role === 'purchasing')>المشتريات</option>
                                <option value="admin" @selected($user->role === 'admin')>مدير النظام</option>
                            </select>
                            <input type="hidden" name="active" value="0">
                            <label class="check-row"><input type="checkbox" name="active" value="1" @checked($user->active)> مفعّل</label>
                            <button class="button button-small" type="submit">حفظ</button>
                        </form>
                    @endif
                </td>
                @endif
            </tr>
        @endforeach</tbody>
    </table></div>
    <div class="pagination">{{ $users->links() }}</div>
</section>

<script>
    const userDialog = document.getElementById('user-dialog');
    if (userDialog) {
        document.getElementById('open-user-dialog').addEventListener('click', () => userDialog.showModal());
        document.getElementById('cancel-user-dialog').addEventListener('click', () => userDialog.close());
        userDialog.addEventListener('click', (event) => {
            if (event.target === userDialog) userDialog.close();
        });
        @if($errors->any())
            userDialog.showModal();
        @endif
    }
</script>
@endsection
