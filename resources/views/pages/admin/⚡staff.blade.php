<?php

use App\Models\User;
use App\Support\AuditLogger;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Permission\Models\Role;

new
#[Layout('layouts.app')]
#[Title('Admin: Staff')]
class extends Component {
    public ?int $editingId = null;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    /** @var array<int, string> */
    public array $selectedRoles = [];

    public function with(): array
    {
        return [
            'staffMembers' => User::query()
                ->staff()
                ->with('roles')
                ->orderBy('name')
                ->get(),
            'roles' => Role::query()->orderBy('name')->get(),
        ];
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->editingId = 0;
    }

    public function edit(int $userId): void
    {
        $user = User::query()->staff()->with('roles')->findOrFail($userId);

        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->password = '';
        $this->password_confirmation = '';
        $this->selectedRoles = $user->roles->pluck('name')->all();
        $this->resetValidation();
    }

    public function save(): void
    {
        $isEditing = (int) $this->editingId > 0;

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($isEditing ? $this->editingId : null),
            ],
            'selectedRoles' => ['required', 'array', 'min:1'],
            'selectedRoles.*' => ['string', 'exists:roles,name'],
        ];

        if ($isEditing) {
            $rules['password'] = ['nullable', 'string', Password::default(), 'confirmed'];
        } else {
            $rules['password'] = ['required', 'string', Password::default(), 'confirmed'];
        }

        $validated = $this->validate($rules);
        $roles = array_values($validated['selectedRoles']);
        $isSuperUser = in_array('Super User', $roles, true);

        if ($isEditing) {
            $user = User::query()->staff()->findOrFail($this->editingId);

            if ($user->is(auth()->user()) && ! $isSuperUser) {
                $this->addError('selectedRoles', 'You cannot remove Super User from your own account.');

                return;
            }

            $payload = [
                'name' => $validated['name'],
                'email' => $validated['email'],
                'is_admin' => $isSuperUser,
            ];

            if (filled($validated['password'] ?? null)) {
                $payload['password'] = $validated['password'];
            }

            $user->forceFill($payload)->save();
            $user->syncRoles($roles);

            AuditLogger::log('staff', 'staff member updated', 'updated', $user->fresh(), [
                'email' => $user->email,
                'roles' => $roles,
            ]);

            Flux::toast(variant: 'success', text: 'Staff member updated.');
        } else {
            $user = User::query()->create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'is_admin' => $isSuperUser,
                'email_verified_at' => now(),
            ]);

            $user->syncRoles($roles);

            AuditLogger::log('staff', 'staff member created', 'created', $user, [
                'email' => $user->email,
                'roles' => $roles,
            ]);

            Flux::toast(variant: 'success', text: 'Staff member created.');
        }

        $this->resetForm();
    }

    public function removeAccess(int $userId): void
    {
        $user = User::query()->staff()->findOrFail($userId);

        if ($user->is(auth()->user())) {
            Flux::toast(variant: 'danger', text: 'You cannot remove your own staff access.');

            return;
        }

        $user->syncRoles([]);
        $user->forceFill(['is_admin' => false])->save();

        AuditLogger::log('staff', 'staff access removed', 'access_removed', $user->fresh(), [
            'email' => $user->email,
        ]);

        Flux::toast(variant: 'success', text: 'Staff access removed. The account remains as a normal user.');
    }

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->email = '';
        $this->password = '';
        $this->password_confirmation = '';
        $this->selectedRoles = [];
        $this->resetValidation();
    }
}; ?>

<div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <flux:heading size="xl">Staff</flux:heading>
            <flux:text class="mt-1">Create and manage people who can access the admin. Assign roles here, or adjust roles on the Roles page.</flux:text>
        </div>
        <flux:button type="button" wire:click="openCreate" class="bg-accent! text-accent-foreground!">Add staff</flux:button>
    </div>

    @if ($editingId !== null)
        <form wire:submit="save" class="space-y-4 rounded-xl border border-zinc-200 bg-white p-4 sm:p-6 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading size="lg">{{ $editingId ? 'Edit staff' : 'Add staff' }}</flux:heading>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>Name</flux:label>
                    <flux:input wire:model="name" />
                    <flux:error name="name" />
                </flux:field>
                <flux:field>
                    <flux:label>Email</flux:label>
                    <flux:input type="email" wire:model="email" autocomplete="off" />
                    <flux:error name="email" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ $editingId ? 'New password (optional)' : 'Password' }}</flux:label>
                    <flux:input type="password" wire:model="password" autocomplete="new-password" viewable />
                    <flux:error name="password" />
                </flux:field>
                <flux:field>
                    <flux:label>Confirm password</flux:label>
                    <flux:input type="password" wire:model="password_confirmation" autocomplete="new-password" viewable />
                </flux:field>
            </div>

            <div>
                <flux:label>Roles</flux:label>
                <flux:text class="mt-1 text-xs">Choose at least one. Super User also marks the account as a full admin.</flux:text>
                <div class="mt-2 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($roles as $role)
                        <label wire:key="staff-role-{{ $role->id }}" class="flex items-center gap-2 text-sm">
                            <input type="checkbox" wire:model="selectedRoles" value="{{ $role->name }}" class="rounded border-zinc-300" />
                            <span>{{ $role->name }}</span>
                        </label>
                    @endforeach
                </div>
                <flux:error name="selectedRoles" />
            </div>

            <div class="flex gap-2">
                <flux:button type="submit" class="bg-accent! text-accent-foreground!">Save staff</flux:button>
                <flux:button type="button" variant="ghost" wire:click="resetForm">Cancel</flux:button>
            </div>
        </form>
    @endif

    <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
        <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
            <thead class="bg-zinc-50 text-left dark:bg-zinc-800">
                <tr>
                    <th class="px-4 py-3 font-medium">Name</th>
                    <th class="px-4 py-3 font-medium">Email</th>
                    <th class="px-4 py-3 font-medium">Roles</th>
                    <th class="px-4 py-3 font-medium"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($staffMembers as $member)
                    <tr wire:key="staff-{{ $member->id }}">
                        <td class="px-4 py-3 font-medium">
                            {{ $member->name }}
                            @if ($member->is_admin)
                                <flux:badge size="sm" color="amber" class="ms-1">Admin</flux:badge>
                            @endif
                        </td>
                        <td class="px-4 py-3">{{ $member->email }}</td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-1">
                                @forelse ($member->roles as $role)
                                    <flux:badge size="sm" color="zinc">{{ $role->name }}</flux:badge>
                                @empty
                                    <span class="text-xs text-zinc-500">No roles</span>
                                @endforelse
                            </div>
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <flux:button size="sm" variant="ghost" wire:click="edit({{ $member->id }})">Edit</flux:button>
                            @unless ($member->is(auth()->user()))
                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    wire:click="removeAccess({{ $member->id }})"
                                    wire:confirm="Remove admin access for {{ $member->name }}? Their login will remain as a normal customer account."
                                >
                                    Remove access
                                </flux:button>
                            @endunless
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-8 text-center text-zinc-500">No staff members yet. Add one to get started.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
