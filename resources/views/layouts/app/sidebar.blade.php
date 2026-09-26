<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        @php
            $user = auth()->user();
            $can = fn (string $permission): bool => $user->is_admin || $user->can($permission);
        @endphp
        <flux:sidebar
            sticky
            collapsible="mobile"
            class="admin-sidebar flex h-svh max-h-svh flex-col overflow-hidden border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900"
        >
            <div class="shrink-0 border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:sidebar.header>
                    <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                    <flux:sidebar.collapse class="lg:hidden" />
                </flux:sidebar.header>

                <div class="px-4 pb-4">
                    <div class="truncate text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $user->name }}</div>
                    <div class="truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $user->email }}</div>
                </div>
            </div>

            <div class="admin-sidebar-nav min-h-0 flex-1 overflow-y-auto overscroll-contain">
                <flux:sidebar.nav>
                <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                    {{ __('Dashboard') }}
                </flux:sidebar.item>
                @if ($user->canAccessAdmin())
                    <div class="mx-3 my-2 border-t border-zinc-200 dark:border-zinc-700" role="separator"></div>

                    @if ($can('auctions.view') || $can('auctions.manage') || $can('orders.view') || $can('orders.manage') || $can('products.view') || $can('products.manage') || $can('guest-listings.view') || $can('guest-listings.manage') || $can('logistics.view') || $can('logistics.manage'))
                        <flux:sidebar.group :heading="__('Sales & shop')" class="grid">
                            @if ($can('auctions.view') || $can('auctions.manage'))
                                <flux:sidebar.item icon="scale" :href="route('admin.auctions')" :current="request()->routeIs('admin.auctions*')" wire:navigate>
                                    {{ __('Sales & Auctions') }}
                                </flux:sidebar.item>
                            @endif
                            @if ($can('orders.view') || $can('orders.manage'))
                                <flux:sidebar.item icon="truck" :href="route('admin.orders')" :current="request()->routeIs('admin.orders*')" wire:navigate>
                                    {{ __('Shop Orders') }}
                                </flux:sidebar.item>
                            @endif
                            @if ($can('products.view') || $can('products.manage'))
                                <flux:sidebar.item icon="squares-2x2" :href="route('admin.categories')" :current="request()->routeIs('admin.categories')" wire:navigate>
                                    {{ __('Categories') }}
                                </flux:sidebar.item>
                                <flux:sidebar.item icon="shopping-bag" :href="route('admin.products')" :current="request()->routeIs('admin.products')" wire:navigate>
                                    {{ __('Products') }}
                                </flux:sidebar.item>
                            @endif
                            @if ($can('guest-listings.view') || $can('guest-listings.manage'))
                                <flux:sidebar.item icon="clipboard-document-check" :href="route('admin.guest-listings')" :current="request()->routeIs('admin.guest-listings')" wire:navigate>
                                    {{ __('Guest listings') }}
                                </flux:sidebar.item>
                            @endif
                            @if ($can('logistics.view') || $can('logistics.manage'))
                                <flux:sidebar.item icon="map" :href="route('admin.logistics')" :current="request()->routeIs('admin.logistics')" wire:navigate>
                                    {{ __('Logistics') }}
                                </flux:sidebar.item>
                            @endif
                        </flux:sidebar.group>

                        <div class="mx-3 my-2 border-t border-zinc-200 dark:border-zinc-700" role="separator"></div>
                    @endif

                    @if ($can('customers.view') || $can('customers.manage'))
                        <flux:sidebar.group :heading="__('People')" class="grid">
                            <flux:sidebar.item icon="users" :href="route('admin.customers')" :current="request()->routeIs('admin.customers') || request()->routeIs('admin.customer-verifications')" wire:navigate>
                                {{ __('Customers') }}
                            </flux:sidebar.item>
                            <flux:sidebar.item icon="megaphone" :href="route('admin.subscribers')" :current="request()->routeIs('admin.subscribers')" wire:navigate>
                                {{ __('Subscribers') }}
                            </flux:sidebar.item>
                        </flux:sidebar.group>

                        <div class="mx-3 my-2 border-t border-zinc-200 dark:border-zinc-700" role="separator"></div>
                    @endif

                    @if ($can('tickets.view') || $can('tickets.manage') || $can('reports.view'))
                        <flux:sidebar.group :heading="__('Support & reports')" class="grid">
                            @if ($can('tickets.view') || $can('tickets.manage'))
                                <flux:sidebar.item icon="inbox" :href="route('admin.tickets')" :current="request()->routeIs('admin.tickets*')" wire:navigate>
                                    {{ __('Support tickets') }}
                                </flux:sidebar.item>
                            @endif
                            @if ($can('reports.view'))
                                <flux:sidebar.item icon="chart-bar" :href="route('admin.reports')" :current="request()->routeIs('admin.reports')" wire:navigate>
                                    {{ __('Reports') }}
                                </flux:sidebar.item>
                            @endif
                        </flux:sidebar.group>

                        <div class="mx-3 my-2 border-t border-zinc-200 dark:border-zinc-700" role="separator"></div>
                    @endif

                    @if ($can('content.manage'))
                        <flux:sidebar.group :heading="__('Content')" class="grid">
                            <flux:sidebar.item icon="chat-bubble-left-right" :href="route('admin.testimonials')" :current="request()->routeIs('admin.testimonials')" wire:navigate>
                                {{ __('Testimonials') }}
                            </flux:sidebar.item>
                            <flux:sidebar.item icon="document-text" :href="route('admin.legal')" :current="request()->routeIs('admin.legal')" wire:navigate>
                                {{ __('Legal') }}
                            </flux:sidebar.item>
                            <flux:sidebar.item icon="book-open" :href="route('admin.how-it-works')" :current="request()->routeIs('admin.how-it-works')" wire:navigate>
                                {{ __('How it works') }}
                            </flux:sidebar.item>
                            <flux:sidebar.item icon="question-mark-circle" :href="route('admin.faqs')" :current="request()->routeIs('admin.faqs')" wire:navigate>
                                {{ __('FAQs') }}
                            </flux:sidebar.item>
                        </flux:sidebar.group>

                        <div class="mx-3 my-2 border-t border-zinc-200 dark:border-zinc-700" role="separator"></div>
                    @endif

                    @if ($can('activity.view'))
                        <flux:sidebar.group :heading="__('System Audit')" class="grid">
                            <flux:sidebar.item icon="clipboard-document-list" :href="route('admin.activity')" :current="request()->routeIs('admin.activity')" wire:navigate>
                                {{ __('Activity Log') }}
                            </flux:sidebar.item>
                        </flux:sidebar.group>

                        <div class="mx-3 my-2 border-t border-zinc-200 dark:border-zinc-700" role="separator"></div>
                    @endif

                    @if ($can('roles.manage') || $can('settings.manage'))
                        <flux:sidebar.group :heading="__('Staff')" class="grid">
                            @if ($can('roles.manage'))
                                <flux:sidebar.item icon="user-group" :href="route('admin.staff')" :current="request()->routeIs('admin.staff')" wire:navigate>
                                    {{ __('Staff') }}
                                </flux:sidebar.item>
                                <flux:sidebar.item icon="shield-check" :href="route('admin.roles')" :current="request()->routeIs('admin.roles')" wire:navigate>
                                    {{ __('Roles') }}
                                </flux:sidebar.item>
                            @endif
                            @if ($can('settings.manage'))
                                <flux:sidebar.item icon="cog-6-tooth" :href="route('admin.settings')" :current="request()->routeIs('admin.settings', 'profile.edit', 'security.edit')" wire:navigate>
                                    {{ __('Settings') }}
                                </flux:sidebar.item>
                            @endif
                        </flux:sidebar.group>
                    @elseif ($user->is_admin)
                        <flux:sidebar.item icon="cog-6-tooth" :href="route('admin.settings')" :current="request()->routeIs('admin.settings', 'profile.edit', 'security.edit')" wire:navigate>
                            {{ __('Settings') }}
                        </flux:sidebar.item>
                    @endif
                @endif
                </flux:sidebar.nav>
            </div>

            <div class="flex shrink-0 items-center gap-2 border-t border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-900">
                @if ($user->canAccessAdmin())
                    <livewire:admin-ticket-bell placement="up" wire:key="ticket-bell-sidebar" />
                @endif
                <x-admin-logout-button class="min-w-0 flex-1" />
            </div>
        </flux:sidebar>

        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <div class="min-w-0 px-2">
                <div class="truncate text-sm font-semibold">{{ $user->name }}</div>
                <div class="truncate text-xs text-zinc-500">{{ $user->email }}</div>
            </div>

            <flux:spacer />

            <div class="flex shrink-0 items-center gap-1">
                @if ($user->canAccessAdmin())
                    <livewire:admin-ticket-bell placement="down" wire:key="ticket-bell-header" />
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
