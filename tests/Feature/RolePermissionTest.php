<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

test('role permission seeder creates roles and assigns super user to admins', function () {
    $admin = User::factory()->admin()->create();
    $staff = User::factory()->create(['is_admin' => false]);

    $this->seed(RolePermissionSeeder::class);

    expect(Role::query()->where('name', 'Super User')->exists())->toBeTrue()
        ->and(Role::query()->where('name', 'Marketing')->exists())->toBeTrue()
        ->and(Role::query()->where('name', 'Finance')->exists())->toBeTrue()
        ->and(Role::query()->where('name', 'Customer Care')->exists())->toBeTrue()
        ->and($admin->fresh()->hasRole('Super User'))->toBeTrue()
        ->and($admin->can('roles.manage'))->toBeTrue()
        ->and($admin->can('reports.view'))->toBeTrue()
        ->and($staff->fresh()->hasRole('Super User'))->toBeFalse();
});

test('admin with super user can open roles page', function () {
    $admin = User::factory()->admin()->create();
    $this->seed(RolePermissionSeeder::class);

    $this->actingAs($admin->fresh())
        ->get(route('admin.roles'))
        ->assertOk()
        ->assertSee('Roles & permissions', false);
});

test('customer care role has ticket permissions but not reports', function () {
    $this->seed(RolePermissionSeeder::class);

    $care = User::factory()->create(['is_admin' => false]);
    $care->assignRole('Customer Care');

    expect($care->canAccessAdmin())->toBeTrue()
        ->and($care->can('access.admin'))->toBeTrue()
        ->and($care->can('tickets.view'))->toBeTrue()
        ->and($care->can('tickets.manage'))->toBeTrue()
        ->and($care->can('customers.view'))->toBeTrue()
        ->and($care->can('reports.view'))->toBeFalse()
        ->and($care->can('roles.manage'))->toBeFalse();
});

test('customer care can access admin tickets but lacks reports permission', function () {
    $this->seed(RolePermissionSeeder::class);

    $care = User::factory()->create(['is_admin' => false]);
    $care->assignRole('Customer Care');

    $this->actingAs($care->fresh())
        ->get(route('admin.tickets'))
        ->assertOk();

    expect($care->fresh()->can('reports.view'))->toBeFalse();
});

test('users with only a staff role can access admin via canAccessAdmin', function () {
    $this->seed(RolePermissionSeeder::class);

    $finance = User::factory()->create(['is_admin' => false]);
    $finance->assignRole('Finance');

    expect($finance->canAccessAdmin())->toBeTrue();

    $this->actingAs($finance->fresh())
        ->get(route('admin.logistics'))
        ->assertOk()
        ->assertSee('Logistics');
});

test('admins can delete a custom role but not Super User', function () {
    $this->seed(RolePermissionSeeder::class);

    $admin = User::factory()->admin()->create();
    $admin->assignRole('Super User');

    $custom = Role::findOrCreate('Super Admin', 'web');

    Livewire::actingAs($admin)
        ->test('pages::admin.roles')
        ->call('delete', $custom->id)
        ->assertHasNoErrors();

    expect(Role::query()->where('name', 'Super Admin')->exists())->toBeFalse();

    $superUser = Role::query()->where('name', 'Super User')->firstOrFail();

    Livewire::actingAs($admin)
        ->test('pages::admin.roles')
        ->call('delete', $superUser->id);

    expect(Role::query()->where('name', 'Super User')->exists())->toBeTrue();
});
