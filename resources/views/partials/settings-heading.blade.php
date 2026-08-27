<div class="relative mb-6 w-full">
    <flux:heading size="xl" level="1">{{ __('Settings') }}</flux:heading>
    <flux:subheading size="lg" class="mb-6">
        @if (auth()->user()?->is_admin)
            {{ __('Company contact details, your profile, password, and appearance.') }}
        @else
            {{ __('Manage your profile and account settings') }}
        @endif
    </flux:subheading>

    <x-settings-tabs class="mb-6" />

    <flux:separator variant="subtle" />
</div>
