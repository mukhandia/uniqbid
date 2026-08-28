<?php

use App\Models\Testimonial;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts.site')]
#[Title('Testimonials: What UniqBid Customers Say')]
class extends Component {
    public function with(): array
    {
        return [
            'testimonials' => Testimonial::query()->published()->ordered()->get(),
        ];
    }
}; ?>

<div class="bg-surface">
    <div class="mx-auto max-w-7xl px-4 py-12">
        <div class="max-w-2xl">
            <p class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Customer stories</p>
            <h1 class="mt-2 text-3xl font-bold text-navy sm:text-4xl">What people say about UniqBid</h1>
            <p class="mt-3 text-base text-muted-foreground">
                Real feedback from Kenyan bidders and shoppers using UniqBid.
            </p>
        </div>

        <div class="mt-10 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            @forelse ($testimonials as $item)
                <article class="flex flex-col rounded-2xl border border-border bg-white p-6 shadow-sm" wire:key="testimonial-{{ $item->id }}">
                    <div class="flex items-center gap-1 text-gold" aria-label="{{ $item->rating }} out of 5 stars">
                        @for ($i = 1; $i <= 5; $i++)
                            <flux:icon.star
                                @class([
                                    'size-4',
                                    'fill-current' => $i <= $item->rating,
                                    'opacity-30' => $i > $item->rating,
                                ])
                            />
                        @endfor
                    </div>

                    <blockquote class="mt-4 flex-1 text-sm leading-relaxed text-zinc-800">
                        “{{ $item->quote }}”
                    </blockquote>

                    <div class="mt-6 flex items-center gap-3 border-t border-border pt-4">
                        <div class="grid h-10 w-10 place-items-center rounded-full bg-navy text-sm font-bold text-navy-foreground">
                            {{ strtoupper(substr($item->name, 0, 1)) }}
                        </div>
                        <div class="min-w-0">
                            <div class="truncate text-sm font-semibold text-navy">{{ $item->name }}</div>
                            <div class="truncate text-xs text-muted-foreground">
                                {{ $item->location }} · {{ $item->role }}
                            </div>
                            <div class="mt-0.5 truncate text-xs font-medium text-zinc-600">{{ $item->item }}</div>
                        </div>
                    </div>
                </article>
            @empty
                <div class="col-span-full rounded-2xl border border-dashed border-border bg-white p-10 text-center text-sm text-muted-foreground">
                    Customer stories will appear here soon.
                </div>
            @endforelse
        </div>

        <div class="mt-12 rounded-2xl border border-border bg-white p-8 text-center shadow-sm sm:p-10">
            <h2 class="text-xl font-bold text-navy sm:text-2xl">Ready to place a bid on a live auction?</h2>
            <p class="mx-auto mt-2 max-w-lg text-sm text-muted-foreground">
                Browse open sales, pay for your bid with M-Pesa, and see why Kenyans keep coming back.
            </p>
            <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
                <flux:button href="{{ route('auctions.index') }}" class="bg-accent! text-accent-foreground!">
                    Browse live auctions
                </flux:button>
                <flux:button href="{{ route('past-sales') }}" variant="outline">
                    See past sales
                </flux:button>
            </div>
        </div>
    </div>
</div>
