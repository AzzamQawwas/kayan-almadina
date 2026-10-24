<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory;
    use Notifiable;

    public const ROLE_ADMIN = 'admin';
    public const ROLE_REPRESENTATIVE = 'representative';
    public const ROLE_PURCHASING = 'purchasing';

    protected $fillable = [
        'name',
        'email',
        'avatar_path',
        'avatar_mime_type',
        'password',
        'role',
        'active',
        'permissions',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'active' => 'boolean',
            'permissions' => 'array',
        ];
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'representative_id');
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class);
    }

    public function roleLabel(): string
    {
        return match ($this->role) {
            self::ROLE_ADMIN => 'مدير النظام',
            self::ROLE_REPRESENTATIVE => 'مندوب',
            self::ROLE_PURCHASING => 'المشتريات',
            default => 'غير معروف',
        };
    }

    public static function permissionDefinitions(): array
    {
        return [
            'dashboard.view' => ['label' => 'عرض لوحة التحكم', 'group' => 'لوحة التحكم', 'roles' => [self::ROLE_ADMIN, self::ROLE_REPRESENTATIVE, self::ROLE_PURCHASING]],
            'orders.view' => ['label' => 'عرض الطلبات وتفاصيلها', 'group' => 'الطلبات', 'roles' => [self::ROLE_ADMIN, self::ROLE_REPRESENTATIVE, self::ROLE_PURCHASING]],
            'orders.create' => ['label' => 'إنشاء طلب', 'group' => 'الطلبات', 'roles' => [self::ROLE_ADMIN, self::ROLE_REPRESENTATIVE]],
            'orders.edit' => ['label' => 'تعديل بيانات الطلب', 'group' => 'الطلبات', 'roles' => [self::ROLE_ADMIN]],
            'orders.delete' => ['label' => 'حذف الطلب', 'group' => 'الطلبات', 'roles' => [self::ROLE_ADMIN]],
            'orders.purchase' => ['label' => 'تأكيد المورد والأسعار', 'group' => 'الطلبات', 'roles' => [self::ROLE_ADMIN, self::ROLE_PURCHASING]],
            'orders.report_shortage' => ['label' => 'تسجيل نقص في الطلب', 'group' => 'الطلبات', 'roles' => [self::ROLE_ADMIN, self::ROLE_REPRESENTATIVE]],
            'orders.resolve_shortage' => ['label' => 'معالجة النواقص', 'group' => 'الطلبات', 'roles' => [self::ROLE_ADMIN, self::ROLE_PURCHASING]],
            'orders.close' => ['label' => 'تأكيد التسليم وإغلاق الطلب', 'group' => 'الطلبات', 'roles' => [self::ROLE_ADMIN, self::ROLE_REPRESENTATIVE]],
            'customers.view' => ['label' => 'عرض العملاء', 'group' => 'العملاء', 'roles' => [self::ROLE_ADMIN, self::ROLE_REPRESENTATIVE]],
            'customers.create' => ['label' => 'إضافة عميل', 'group' => 'العملاء', 'roles' => [self::ROLE_ADMIN, self::ROLE_REPRESENTATIVE]],
            'customers.update' => ['label' => 'تعديل بيانات العميل وتفعيله', 'group' => 'العملاء', 'roles' => [self::ROLE_ADMIN, self::ROLE_REPRESENTATIVE]],
            'customers.delete' => ['label' => 'حذف عميل', 'group' => 'العملاء', 'roles' => [self::ROLE_ADMIN, self::ROLE_REPRESENTATIVE]],
            'suppliers.view' => ['label' => 'عرض الموردين', 'group' => 'الموردون', 'roles' => [self::ROLE_ADMIN, self::ROLE_PURCHASING]],
            'suppliers.create' => ['label' => 'إضافة مورد', 'group' => 'الموردون', 'roles' => [self::ROLE_ADMIN, self::ROLE_PURCHASING]],
            'suppliers.update' => ['label' => 'تعديل بيانات المورد وتفعيله', 'group' => 'الموردون', 'roles' => [self::ROLE_ADMIN, self::ROLE_PURCHASING]],
            'suppliers.delete' => ['label' => 'حذف مورد', 'group' => 'الموردون', 'roles' => [self::ROLE_ADMIN, self::ROLE_PURCHASING]],
            'reports.view' => ['label' => 'عرض التقارير والأرباح', 'group' => 'التقارير', 'roles' => [self::ROLE_ADMIN, self::ROLE_PURCHASING]],
            'users.view' => ['label' => 'عرض صفحة المستخدمين', 'group' => 'إدارة النظام', 'roles' => [self::ROLE_ADMIN]],
            'users.create' => ['label' => 'إضافة مستخدم', 'group' => 'إدارة النظام', 'roles' => [self::ROLE_ADMIN]],
            'users.update' => ['label' => 'تعديل بيانات المستخدم', 'group' => 'إدارة النظام', 'roles' => [self::ROLE_ADMIN]],
            'users.manage' => ['label' => 'تعديل صلاحية المستخدم وتفعيل حسابه', 'group' => 'إدارة النظام', 'roles' => [self::ROLE_ADMIN]],
            'users.permissions' => ['label' => 'إدارة صلاحيات المستخدمين', 'group' => 'إدارة النظام', 'roles' => [self::ROLE_ADMIN]],
            'units.view' => ['label' => 'عرض وحدات المنتجات', 'group' => 'وحدات المنتجات', 'roles' => [self::ROLE_ADMIN]],
            'units.create' => ['label' => 'إضافة وحدة', 'group' => 'وحدات المنتجات', 'roles' => [self::ROLE_ADMIN]],
            'units.update' => ['label' => 'تفعيل أو إيقاف وحدة', 'group' => 'وحدات المنتجات', 'roles' => [self::ROLE_ADMIN]],
            'units.delete' => ['label' => 'حذف وحدة', 'group' => 'وحدات المنتجات', 'roles' => [self::ROLE_ADMIN]],
        ];
    }

    public static function defaultPermissionsForRole(string $role): array
    {
        return array_keys(array_filter(
            self::permissionDefinitions(),
            static fn (array $permission): bool => in_array($role, $permission['roles'], true),
        ));
    }

    public static function permissionRequirements(): array
    {
        return [
            'orders.create' => ['orders.view', 'customers.view'],
            'orders.edit' => 'orders.view',
            'orders.delete' => 'orders.view',
            'orders.purchase' => 'orders.view',
            'orders.report_shortage' => 'orders.view',
            'orders.resolve_shortage' => 'orders.view',
            'orders.close' => 'orders.view',
            'customers.create' => 'customers.view',
            'customers.update' => 'customers.view',
            'customers.delete' => 'customers.view',
            'suppliers.create' => 'suppliers.view',
            'suppliers.update' => 'suppliers.view',
            'suppliers.delete' => 'suppliers.view',
            'users.create' => 'users.view',
            'users.update' => 'users.view',
            'users.manage' => 'users.view',
            'users.permissions' => 'users.view',
            'units.create' => 'units.view',
            'units.update' => 'units.view',
            'units.delete' => 'units.view',
        ];
    }

    public function availablePermissionKeys(): array
    {
        return array_keys(self::permissionDefinitions());
    }

    public function effectivePermissions(): array
    {
        $granted = $this->permissions ?? self::defaultPermissionsForRole($this->role);
        $granted = array_values(array_intersect($this->availablePermissionKeys(), $granted));
        $requirements = self::permissionRequirements();

        return array_values(array_filter(
            $granted,
            static fn (string $permission): bool => ! isset($requirements[$permission])
                || collect((array) $requirements[$permission])->every(
                    static fn (string $requiredPermission): bool => in_array($requiredPermission, $granted, true),
                ),
        ));
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->effectivePermissions(), true);
    }

    public function hasAnyPermission(array $permissions): bool
    {
        return collect($permissions)->contains(fn (string $permission): bool => $this->hasPermission($permission));
    }
}
