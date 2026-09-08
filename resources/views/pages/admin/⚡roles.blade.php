<?php

use App\Models\User;
use App\Support\AuditLogger;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

new
#[Layout('layouts.app')]
#[Title('Admin: Roles')]
class extends Component {
    public ?int $editingRoleId = null;

    public string $roleName = '';

    /** @var array<int, string> */
    public array $selectedPermissions = [];

    public string $assignUserId = '';

    public string $assignRoleName = '';

    public function with(): array
    {
        return [
            'roles' => Role::query()->with('permissions')->withCount('users')->orderBy('name')->get(),
            'permissions' => Permission::query()->orderBy('name')->get(),
            'roleOptions' => Role::query()->orderBy('name')->pluck('name', 'name'),
            'staffOptions' => User::query()
                ->staff()
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
        ];
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->editingRoleId = 0;
    }

    public function edit(int $roleId): void
    {
        $role = Role::query()->with('permissions')->findOrFail($roleId);
        $this->editingRoleId = $role->id;
        $this->roleName = $role->name;
        $this->selectedPermissions = $role->permissions->pluck('name')->all();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'roleName' => ['required', 'string', 'max:100'],
            'selectedPermissions' => ['array'],
            'selectedPermissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $name = trim($validated['roleName']);

        if ($this->editingRoleId) {
            $role = Role::query()->findOrFail($this->editingRoleId);
            $role->name = $name;
            $role->save();
            $role->syncPermissions($validated['selectedPermissions'] ?? []);
            AuditLogger::log('roles', 'role updated', 'updated', null, [
                'role' => $role->name,
                'permissions' => $validated['selectedPermissions'] ?? [],
            ]);
            Flux::toast(variant: 'success', text: 'Role updated.');
        } else {
            $role = Role::findOrCreate($name, 'web');
            $role->syncPermissions($validated['selectedPermissions'] ?? []);
            AuditLogger::log('roles', 'role created', 'created', null, [
                'role' => $role->name,
                'permissions' => $validated['selectedPermissions'] ?? [],
            ]);
            Flux::toast(variant: 'success', text: 'Role created.');
        }

        $this->resetForm();
    }

    public function assignRoleToUser(): void
    {
        $validated = $this->validate([
            'assignUserId' => ['required', 'integer', 'exists:users,id'],
            'assignRoleName' => ['required', 'string', 'exists:roles,name'],
        ]);

        $user = User::query()->staff()->findOrFail((int) $validated['assignUserId']);

        $user->assignRole($validated['assignRoleName']);

        if ($validated['assignRoleName'] === 'Super User') {
            $user->forceFill(['is_admin' => true])->save();
        }

        AuditLogger::log('roles', 'role assigned to user', 'assigned', $user, [
            'role' => $validated['assignRoleName'],
            'email' => $user->email,
        ]);
        Flux::toast(variant: 'success', text: "Assigned {$validated['assignRoleName']} to {$user->name}.");
        $this->assignUserId = '';
        $this->assignRoleName = '';
    }

    public function resetForm(): void
    {
        $this->editingRoleId = null;
        $this->roleName = '';
        $this->selectedPermissions = [];
        $this->resetValidation();
    }
}; ?>

<div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <flux:heading size="xl">Roles & permissions</flux:heading>
            <flux:text class="mt-1">Define permission sets, then assign them to staff from the dropdown below. Create staff accounts under Staff.</flux:text>
        </div>
        <flux:button type="button" wire:click="openCreate" class="bg-accent! text-accent-foreground!">New role</flux:button>
    </div>

    @if ($editingRoleId !== null)
        <form wire:submit="save" class="space-y-4 rounded-xl border border-zinc-200 bg-white p-4 sm:p-6 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading size="lg">{{ $editingRoleId ? 'Edit role' : 'Create role' }}</flux:heading>
            <flux:field>
                <flux:label>Role name</flux:label>
                <flux:input wire:model="roleName" />
                <flux:error name="roleName" />
            </flux:field>
            <div>
                <flux:label>Permissions</flux:label>
                <div class="mt-2 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($permissions as $permission)
                        <label wire:key="perm-{{ $permission->id }}" class="flex items-center gap-2 text-sm">
                            <input type="checkbox" wire:model="selectedPermissions" value="{{ $permission->name }}" class="rounded border-zinc-300" />
                            <span class="font-mono text-xs">{{ $permission->name }}</span>
                        </label>
                    @endforeach
                </div>
                <flux:error name="selectedPermissions" />
            </div>
            <div class="flex gap-2">
                <flux:button type="submit" class="bg-accent! text-accent-foreground!">Save role</flux:button>
                <flux:button type="button" variant="ghost" wire:click="resetForm">Cancel</flux:button>
            </div>
        </form>
    @endif

    <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
        <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
            <thead class="bg-zinc-50 text-left dark:bg-zinc-800">
                <tr>
                    <th class="px-4 py-3 font-medium">Role</th>
                    <th class="px-4 py-3 font-medium">Permissions</th>
                    <th class="px-4 py-3 font-medium">Users</th>
                    <th class="px-4 py-3 font-medium"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($roles as $role)
                    <tr wire:key="role-{{ $role->id }}">
                        <td class="px-4 py-3 font-medium">{{ $role->name }}</td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-1">
                                @forelse ($role->permissions as $permission)
                                    <flux:badge size="sm" color="zinc">{{ $permission->name }}</flux:badge>
                                @empty
                                    <span class="text-xs text-zinc-500">No permissions</span>
                                @endforelse
                            </div>
                        </td>
                        <td class="px-4 py-3">{{ $role->users_count }}</td>
                        <td class="px-4 py-3 text-right">
                            <flux:button size="sm" variant="ghost" wire:click="edit({{ $role->id }})">Edit</flux:button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-8 text-center text-zinc-500">No roles yet. Seed roles or create one.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <form wire:submit="assignRoleToUser" class="space-y-4 rounded-xl border border-zinc-200 bg-white p-4 sm:p-6 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:heading size="lg">Assign role to staff</flux:heading>
        <flux:text>Only existing staff appear here. Add people under Staff first.</flux:text>
        <div class="grid gap-4 sm:grid-cols-2">
            <flux:field>
                <flux:label>Staff member</flux:label>
                <flux:select wire:model="assignUserId">
                    <flux:select.option value="">Select staff</flux:select.option>
                    @forelse ($staffOptions as $member)
                        <flux:select.option value="{{ $member->id }}">{{ $member->name }} ({{ $member->email }})</flux:select.option>
                    @empty
                        <flux:select.option value="" disabled>No staff yet — create one under Staff</flux:select.option>
                    @endforelse
                </flux:select>
                <flux:error name="assignUserId" />
            </flux:field>
            <flux:field>
                <flux:label>Role</flux:label>
                <flux:select wire:model="assignRoleName">
                    <flux:select.option value="">Select role</flux:select.option>
                    @foreach ($roleOptions as $name)
                        <flux:select.option value="{{ $name }}">{{ $name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="assignRoleName" />
            </flux:field>
        </div>
        <flux:button type="submit" class="bg-accent! text-accent-foreground!">Assign role</flux:button>
    </form>
</div>
