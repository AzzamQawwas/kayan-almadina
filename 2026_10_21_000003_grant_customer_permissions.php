<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $customerPermissions = ['customers.view', 'customers.create', 'customers.update', 'customers.delete'];
        $users = DB::table('users')
            ->whereIn('role', [User::ROLE_ADMIN, User::ROLE_REPRESENTATIVE])
            ->get(['id', 'role', 'permissions']);

        foreach ($users as $user) {
            $permissions = $user->permissions === null
                ? User::defaultPermissionsForRole($user->role)
                : json_decode($user->permissions, true, flags: JSON_THROW_ON_ERROR);
            $updated = array_values(array_unique([...$permissions, ...$customerPermissions]));

            DB::table('users')->where('id', $user->id)->update([
                'permissions' => json_encode($updated, JSON_THROW_ON_ERROR),
            ]);
        }
    }

    public function down(): void
    {
        $customerPermissions = ['customers.view', 'customers.create', 'customers.update', 'customers.delete'];
        $users = DB::table('users')
            ->whereIn('role', [User::ROLE_ADMIN, User::ROLE_REPRESENTATIVE])
            ->get(['id', 'permissions']);

        foreach ($users as $user) {
            if ($user->permissions === null) {
                continue;
            }

            $permissions = json_decode($user->permissions, true, flags: JSON_THROW_ON_ERROR);
            DB::table('users')->where('id', $user->id)->update([
                'permissions' => json_encode(array_values(array_diff($permissions, $customerPermissions)), JSON_THROW_ON_ERROR),
            ]);
        }
    }
};
