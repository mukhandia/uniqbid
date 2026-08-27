<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <div class="px-4 pb-4">
                <div class="truncate text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ auth()->user()->name }}</div>
                <div class="truncate text-xs text-zinc-500 dark:text-zinc-400">{{ auth()->user()->email }}</div>
            </div>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Platform')" class="grid">
                    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard') }}
                    </flux:sidebar.item>
                    @if (auth()->user()->is_admin)
                        <flux:sidebar.item icon="scale" :href="route('admin.auctions')" :current="request()->routeIs('admin.auctions*')" wire:navigate>
                            {{ __('Sales') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="truck" :href="route('admin.orders')" :current="request()->routeIs('admin.orders*')" wire:navigate>
                            {{ __('Orders') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="squares-2x2" :href="route('admin.categories')" :current="request()->routeIs('admin.categories')" wire:navigate>
                            {{ __('Categories') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="shopping-bag" :href="route('admin.products')" :current="request()->routeIs('admin.products')" wire:navigate>
                            {{ __('Products') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="users" :href="route('admin.customers')" :current="request()->routeIs('admin.customers') || request()->routeIs('admin.customer-verifications')" wire:navigate>
                            {{ __('Customers') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="megaphone" :href="route('admin.subscribers')" :current="request()->routeIs('admin.subscribers')" wire:navigate>
                            {{ __('Subscribers') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="inbox" :href="route('admin.tickets')" :current="request()->routeIs('admin.tickets*')" wire:navigate>
                            {{ __('Support tickets') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="cog-6-tooth" :href="route('admin.settings')" :current="request()->routeIs('admin.settings')" wire:navigate>
                            {{ __('Settings') }}
                        </flux:sidebar.item>
                    @endif
                </flux:sidebar.group>
            </flux:sidebar.nav>

            <flux:spacer />

            <div class="hidden items-center gap-2 p-4 lg:flex">
                @if (auth()->user()->is_admin)
                    <livewire:admin-ticket-bell wire:key="ticket-bell-sidebar" />
                @endif
                <x-admin-logout-button class="min-w-0 flex-1" />
            </div>
        </flux:sidebar>

        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <div class="min-w-0 px-2">
                <div class="truncate text-sm font-semibold">{{ auth()->user()->name }}</div>
                <div class="truncate text-xs text-zinc-500">{{ auth()->user()->email }}</div>
            </div>

            <flux:spacer />

            <div class="flex shrink-0 items-center gap-1">
                @if (auth()->user()->is_admin)
                    <livewire:admin-ticket-bell wire:key="ticket-bell-header" />
                @endif
                <x-admin-logout-button :wide="false" />
            </div>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
