<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts.site')]
#[Title('My Account — UniqBid')]
class extends Component {
    //
}; ?>

<div class="mx-auto max-w-3xl px-4 py-12">
    <div class="rounded-2xl border border-border bg-white p-8 shadow-sm">
        <div class="mx-auto grid h-12 w-12 place-items-center rounded-full bg-navy text-navy-foreground">
            <flux:icon.user class="size-6" />
        </div>
        <h1 class="mt-4 text-center text-2xl font-bold text-navy">My Account</h1>
        <p class="mt-2 text-center text-sm text-muted-foreground">
            Bid history, orders, spend limits, and consents will live here. For now, use Fortify auth or continue browsing the public site.
        </p>

        <div class="mt-6 flex flex-wrap justify-center gap-2">
            @auth
                <flux:button href="{{ route('dashboard') }}" class="bg-navy! text-navy-foreground!">
                    Go to dashboard
                </flux:button>
                <flux:button href="{{ route('profile.edit') }}" variant="outline">
                    Profile settings
                </flux:button>
            @else
                <flux:button href="{{ route('login') }}" class="bg-navy! text-navy-foreground!">
                    Sign in
                </flux:button>
                @if (Route::has('register'))
                    <flux:button href="{{ route('register') }}" variant="outline">
                        Create account
                    </flux:button>
                @endif
            @endauth
            <flux:button href="{{ route('auctions.index') }}" variant="ghost">
                Browse auctions
            </flux:button>
        </div>

        <div class="mt-8 rounded-xl bg-surface p-4 text-sm text-muted-foreground">
            Signed in as: <span class="font-medium text-zinc-900">{{ Auth::user()?->email ?? 'Guest' }}</span>
        </div>
    </div>
</div>
