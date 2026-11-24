<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('super user can open staff page', function () {
    $admin = User::factory()->admin()->create();
    $admin->assignRole('Super User');

    $this->actingAs($admin->fresh())
        ->get(route('admin.staff'))
        ->assertOk()
        ->assertSee('Staff')
        ->assertSee('Add staff');
});

test('admins can create a staff member with a role', function () {
    $admin = User::factory()->admin()->create();
    $admin->assignRole('Super User');

    Livewire::actingAs($admin)
        ->test('pages::admin.staff')
        ->call('openCreate')
        ->set('name', 'Finance Lead')
        ->set('email', 'finance.lead@uniqbid.test')
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->set('selectedRoles', ['Finance'])
        ->call('save')
        ->assertHasNoErrors();

    $staff = User::query()->where('email', 'finance.lead@uniqbid.test')->first();

    expect($staff)->not->toBeNull()
        ->and($staff->hasRole('Finance'))->toBeTrue()
        ->and($staff->is_admin)->toBeFalse()
        ->and($staff->canAccessAdmin())->toBeTrue()
        ->and(Activity::query()->where('log_name', 'staff')->where('event', 'created')->exists())->toBeTrue();
});

test('creating staff with super user role marks them as admin', function () {
    $admin = User::factory()->admin()->create();
    $admin->assignRole('Super User');

    Livewire::actingAs($admin)
        ->test('pages::admin.staff')
        ->call('openCreate')
        ->set('name', 'Ops Lead')
        ->set('email', 'ops.lead@uniqbid.test')
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->set('selectedRoles', ['Super User'])
        ->call('save');

    $staff = User::query()->where('email', 'ops.lead@uniqbid.test')->first();

    expect($staff?->is_admin)->toBeTrue()
        ->and($staff->hasRole('Super User'))->toBeTrue();
});

test('roles page assigns a role from the staff dropdown', function () {
    $admin = User::factory()->admin()->create();
    $admin->assignRole('Super User');

    $staff = User::factory()->create([
        'name' => 'Care Agent',
        'email' => 'care@uniqbid.test',
        'is_admin' => false,
    ]);
    $staff->assignRole('Customer Care');

    Livewire::actingAs($admin)
        ->test('pages::admin.roles')
        ->assertSee('Care Agent')
        ->set('assignUserId', (string) $staff->id)
        ->set('assignRoleName', 'Marketing')
        ->call('assignRoleToUser')
        ->assertHasNoErrors();

    expect($staff->fresh()->hasRole('Marketing'))->toBeTrue()
        ->and($staff->fresh()->hasRole('Customer Care'))->toBeTrue();
});

test('roles page does not list ordinary customers in the staff dropdown', function () {
    $admin = User::factory()->admin()->create();
    $admin->assignRole('Super User');

    User::factory()->create([
        'name' => 'Ordinary Customer',
        'email' => 'customer@uniqbid.test',
        'is_admin' => false,
    ]);

    Livewire::actingAs($admin)
        ->test('pages::admin.roles')
        ->assertDontSee('Ordinary Customer')
        ->assertDontSee('customer@uniqbid.test');
});

test('staff access can be removed without deleting the user', function () {
    $admin = User::factory()->admin()->create();
    $admin->assignRole('Super User');

    $staff = User::factory()->create(['is_admin' => false]);
    $staff->assignRole('Finance');

    Livewire::actingAs($admin)
        ->test('pages::admin.staff')
        ->call('removeAccess', $staff->id);

    expect(User::query()->whereKey($staff->id)->exists())->toBeTrue()
        ->and($staff->fresh()->roles)->toHaveCount(0)
        ->and($staff->fresh()->is_admin)->toBeFalse()
        ->and($staff->fresh()->canAccessAdmin())->toBeFalse();
});
