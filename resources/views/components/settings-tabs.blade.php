@php
    $isAdmin = (bool) auth()->user()?->is_admin;
@endphp

<nav
    {{ $attributes->class('flex flex-wrap gap-1 rounded-xl border border-zinc-200 bg-zinc-100 p-1 dark:border-zinc-700 dark:bg-zinc-800/80') }}
    aria-label="{{ __('Settings') }}"
>
    @if ($isAdmin)
        <a
            href="{{ route('admin.settings') }}"
            wire:navigate
            @class([
                'inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition',
                'bg-white text-zinc-900 shadow-sm dark:bg-zinc-900 dark:text-white' => request()->routeIs('admin.settings'),
                'text-zinc-500 hover:bg-white/70 hover:text-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-900/70 dark:hover:text-white' => ! request()->routeIs('admin.settings'),
            ])
            @if (request()->routeIs('admin.settings')) aria-current="page" @endif
        >
            <flux:icon icon="building-office-2" variant="mini" class="size-4" />
            {{ __('Company Info') }}
        </a>
    @endif

    <a
        href="{{ route('profile.edit') }}"
        wire:navigate
        @class([
            'inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition',
            'bg-white text-zinc-900 shadow-sm dark:bg-zinc-900 dark:text-white' => request()->routeIs('profile.edit'),
            'text-zinc-500 hover:bg-white/70 hover:text-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-900/70 dark:hover:text-white' => ! request()->routeIs('profile.edit'),
        ])
        @if (request()->routeIs('profile.edit')) aria-current="page" @endif
    >
        <flux:icon icon="user" variant="mini" class="size-4" />
        {{ __('Profile') }}
    </a>

    <a
        href="{{ route('security.edit') }}"
        wire:navigate
        @class([
            'inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition',
            'bg-white text-zinc-900 shadow-sm dark:bg-zinc-900 dark:text-white' => request()->routeIs('security.edit'),
            'text-zinc-500 hover:bg-white/70 hover:text-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-900/70 dark:hover:text-white' => ! request()->routeIs('security.edit'),
        ])
        @if (request()->routeIs('security.edit')) aria-current="page" @endif
    >
        <flux:icon icon="shield-check" variant="mini" class="size-4" />
        {{ __('Security') }}
    </a>

</nav>
