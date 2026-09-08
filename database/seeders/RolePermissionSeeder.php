<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * @var list<string>
     */
    public const PERMISSIONS = [
        'access.admin',
        'auctions.view',
        'auctions.manage',
        'products.view',
        'products.manage',
        'orders.view',
        'orders.manage',
        'tickets.view',
        'tickets.manage',
        'reports.view',
        'customers.view',
        'customers.manage',
        'content.manage',
        'settings.manage',
        'roles.manage',
        'activity.view',
        'logistics.view',
        'logistics.manage',
    ];

    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $all = Permission::query()->whereIn('name', self::PERMISSIONS)->get();

        $superUser = Role::findOrCreate('Super User', 'web');
        $superUser->syncPermissions($all);

        $marketing = Role::findOrCreate('Marketing', 'web');
        $marketing->syncPermissions([
            'access.admin',
            'auctions.view',
            'auctions.manage',
            'products.view',
            'products.manage',
            'content.manage',
        ]);

        $finance = Role::findOrCreate('Finance', 'web');
        $finance->syncPermissions([
            'access.admin',
            'orders.view',
            'orders.manage',
            'reports.view',
            'logistics.view',
            'logistics.manage',
        ]);

        $customerCare = Role::findOrCreate('Customer Care', 'web');
        $customerCare->syncPermissions([
            'access.admin',
            'tickets.view',
            'tickets.manage',
            'customers.view',
            'customers.manage',
        ]);

        User::query()
            ->where('is_admin', true)
            ->each(fn (User $user) => $user->assignRole($superUser));
    }
}
