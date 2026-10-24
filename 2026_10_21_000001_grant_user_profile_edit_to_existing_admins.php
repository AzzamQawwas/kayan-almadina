<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $admins = DB::table('users')->where('role', 'admin')->get(['id', 'permissions']);

        foreach ($admins as $admin) {
            $permissions = $admin->permissions === null
                ? null
                : json_decode($admin->permissions, true, flags: JSON_THROW_ON_ERROR);

            if ($permissions === null || ! in_array('users.update', $permissions, true)) {
                $permissions ??= \App\Models\User::defaultPermissionsForRole(\App\Models\User::ROLE_ADMIN);
                $permissions[] = 'users.update';
                DB::table('users')->where('id', $admin->id)->update([
                    'permissions' => json_encode(array_values(array_unique($permissions)), JSON_THROW_ON_ERROR),
                ]);
            }
        }
    }

    public function down(): void
    {
        $admins = DB::table('users')->where('role', 'admin')->get(['id', 'permissions']);

        foreach ($admins as $admin) {
            if ($admin->permissions === null) {
                continue;
            }

            $permissions = json_decode($admin->permissions, true, flags: JSON_THROW_ON_ERROR);
            $permissions = array_values(array_diff($permissions, ['users.update']));
            DB::table('users')->where('id', $admin->id)->update([
                'permissions' => json_encode($permissions, JSON_THROW_ON_ERROR),
            ]);
        }
    }
};
