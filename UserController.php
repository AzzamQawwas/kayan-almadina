<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        return view('users.index', [
            'users' => User::query()->orderBy('name')->paginate(20),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', 'in:representative,purchasing,admin'],
            'password' => ['required', 'confirmed', Password::min(12)->letters()->mixedCase()->numbers()->symbols()],
        ]);

        $permissions = array_values(array_intersect(
            User::defaultPermissionsForRole($data['role']),
            $request->user()->effectivePermissions(),
        ));

        User::create($data + [
            'active' => true,
            'permissions' => $permissions,
        ]);

        return back()->with('success', 'تم إنشاء المستخدم. شارك كلمة المرور معه بطريقة آمنة.');
    }

    public function edit(User $user): View
    {
        return view('users.edit', compact('user'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => [
                'nullable',
                'confirmed',
                Password::min(12)->letters()->mixedCase()->numbers()->symbols(),
            ],
        ]);

        $updates = [
            'name' => $data['name'],
            'email' => $data['email'],
        ];
        if (! empty($data['password'])) {
            $updates['password'] = $data['password'];
        }

        $user->update($updates);

        return redirect()->route('users.index')->with('success', 'تم تحديث بيانات المستخدم.');
    }

    public function updateAccess(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'role' => ['required', 'in:representative,purchasing,admin'],
            'active' => ['required', 'boolean'],
        ]);

        DB::transaction(function () use ($request, $user, $data): void {
            $target = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $willDisableSelf = $target->is($request->user()) && (
                ! $data['active'] || $data['role'] !== User::ROLE_ADMIN
            );
            abort_if($willDisableSelf, 422, 'لا يمكن تغيير دور حسابك الإداري أو تعطيله.');

            $removesActiveAdmin = $target->role === User::ROLE_ADMIN
                && $target->active
                && ($data['role'] !== User::ROLE_ADMIN || ! $data['active']);
            if ($removesActiveAdmin) {
                $activeAdmins = User::query()
                    ->where('role', User::ROLE_ADMIN)
                    ->where('active', true)
                    ->lockForUpdate()
                    ->get()
                    ->count();
                abort_if($activeAdmins <= 1, 422, 'يجب الإبقاء على مدير نظام واحد مفعّل على الأقل.');
            }

            $updatedPermissions = $target->role === $data['role']
                ? $target->effectivePermissions()
                : User::defaultPermissionsForRole($data['role']);
            $removesLastPermissionAdmin = $target->role === User::ROLE_ADMIN
                && $target->active
                && $target->hasPermission('users.permissions')
                && ($data['role'] !== User::ROLE_ADMIN
                    || ! $data['active']
                    || ! in_array('users.permissions', $updatedPermissions, true));
            if ($removesLastPermissionAdmin) {
                $otherPermissionAdmins = User::query()
                    ->where('role', User::ROLE_ADMIN)
                    ->where('active', true)
                    ->where('id', '<>', $target->id)
                    ->lockForUpdate()
                    ->get()
                    ->contains(fn (User $admin): bool => $admin->hasPermission('users.permissions'));
                if (! $otherPermissionAdmins) {
                    throw ValidationException::withMessages([
                        'permissions' => 'يجب الإبقاء على مدير نظام مفعّل واحد على الأقل بصلاحية إدارة المستخدمين.',
                    ]);
                }
            }

            $target->update([
                'role' => $data['role'],
                'active' => (bool) $data['active'],
                'permissions' => $updatedPermissions,
            ]);
        });

        return back()->with('success', 'تم تحديث دور المستخدم وحالة حسابه.');
    }

    public function editPermissions(User $user): View
    {
        $definitions = collect(User::permissionDefinitions())
            ->map(fn (array $permission, string $key): array => ['key' => $key] + $permission)
            ->groupBy('group');

        return view('users.permissions', [
            'user' => $user,
            'permissionGroups' => $definitions,
            'grantedPermissions' => $user->effectivePermissions(),
        ]);
    }

    public function updatePermissions(Request $request, User $user): RedirectResponse
    {
        $allowedPermissions = $user->availablePermissionKeys();
        $data = $request->validate([
            'permissions_present' => ['required', 'accepted'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['required', 'string', Rule::in($allowedPermissions)],
        ]);
        $permissions = array_values(array_unique($data['permissions'] ?? []));
        $assignablePermissions = $request->user()->effectivePermissions();

        if (array_diff($permissions, $assignablePermissions) !== []) {
            throw ValidationException::withMessages([
                'permissions' => 'لا يمكنك منح صلاحية لا تملكها في حسابك.',
            ]);
        }

        foreach (User::permissionRequirements() as $permission => $requiredPermissions) {
            $requiredPermissions = (array) $requiredPermissions;
            if (
                in_array($permission, $permissions, true)
                && array_diff($requiredPermissions, $permissions) !== []
            ) {
                throw ValidationException::withMessages([
                    'permissions' => 'يجب منح الصلاحيات المطلوبة ('.implode('، ', $requiredPermissions).') قبل تفعيل الإجراء المرتبط بها.',
                ]);
            }
        }

        DB::transaction(function () use ($user, $permissions): void {
            $target = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_if(
                array_diff($permissions, $target->availablePermissionKeys()) !== [],
                422,
                'تغير دور المستخدم. حدّث الصفحة ثم أعد ضبط صلاحياته.',
            );

            if (
                $target->role === User::ROLE_ADMIN
                && $target->active
                && ! in_array('users.permissions', $permissions, true)
            ) {
                $otherPermissionAdmins = User::query()
                    ->where('role', User::ROLE_ADMIN)
                    ->where('active', true)
                    ->where('id', '<>', $target->id)
                    ->lockForUpdate()
                    ->get()
                    ->contains(fn (User $admin): bool => $admin->hasPermission('users.permissions'));

                if (! $otherPermissionAdmins) {
                    throw ValidationException::withMessages([
                        'permissions' => 'يجب الإبقاء على مدير نظام مفعّل واحد على الأقل بصلاحية إدارة المستخدمين.',
                    ]);
                }
            }

            $target->update(['permissions' => $permissions]);
        });

        return redirect()->route('users.index')->with('success', 'تم تحديث صلاحيات المستخدم.');
    }
}
