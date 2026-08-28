<?php

use App\Models\Faq;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts.site')]
#[Title('FAQs: UniqBid Help')]
class extends Component {
    public string $lang = 'en';

    public function with(): array
    {
        $faqs = Faq::query()->published()->ordered()->get();

        return [
            'faqs' => $faqs,
            'grouped' => $faqs->groupBy('category'),
            'categories' => Faq::categories(),
        ];
    }
}; ?>

<div class="bg-surface">
    <div class="mx-auto max-w-4xl px-4 py-12">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Help centre</p>
                <h1 class="mt-2 text-3xl font-bold text-navy sm:text-4xl">
                    {{ $lang === 'en' ? 'Frequently asked questions' : 'Maswali yanayoulizwa mara kwa mara' }}
                </h1>
                <p class="mt-3 max-w-2xl text-base text-muted-foreground">
                    {{ $lang === 'en'
                        ? 'Quick answers about bidding, the shop, and your account.'
                        : 'Majibu ya haraka kuhusu zabuni, duka, na akaunti yako.' }}
                </p>
            </div>
            <div class="inline-flex rounded-md border border-border bg-white p-1 text-xs">
                <button type="button" wire:click="$set('lang', 'en')" @class(['rounded px-2.5 py-1', 'bg-navy text-navy-foreground' => $lang === 'en', 'text-muted-foreground' => $lang !== 'en'])>English</button>
                <button type="button" wire:click="$set('lang', 'sw')" @class(['rounded px-2.5 py-1', 'bg-navy text-navy-foreground' => $lang === 'sw', 'text-muted-foreground' => $lang !== 'sw'])>Kiswahili</button>
            </div>
        </div>

        <div class="mt-10 space-y-8">
            @forelse ($grouped as $category => $items)
                <section wire:key="faq-group-{{ $category }}">
                    <h2 class="text-lg font-semibold text-navy">{{ $categories[$category] ?? ucfirst($category) }}</h2>
                    <div class="mt-4 divide-y divide-border rounded-2xl border border-border bg-white">
                        @foreach ($items as $faq)
                            <details wire:key="faq-{{ $faq->id }}" class="group px-5 py-4">
                                <summary class="cursor-pointer list-none font-medium text-navy marker:content-none [&::-webkit-details-marker]:hidden">
                                    <div class="flex items-start justify-between gap-3">
                                        <span>{{ $faq->questionFor($lang) }}</span>
                                        <flux:icon.chevron-down class="mt-0.5 size-4 shrink-0 transition group-open:rotate-180" />
                                    </div>
                                </summary>
                                <p class="mt-3 text-sm leading-relaxed text-muted-foreground">
                                    {{ $faq->answerFor($lang) }}
                                </p>
                            </details>
                        @endforeach
                    </div>
                </section>
            @empty
                <div class="rounded-2xl border border-dashed border-border bg-white p-10 text-center text-sm text-muted-foreground">
                    FAQs will appear here soon.
                </div>
            @endforelse
        </div>

        <div class="mt-10 rounded-2xl border border-border bg-white p-6 text-center">
            <p class="text-sm text-muted-foreground">
                {{ $lang === 'en' ? 'Still need help?' : 'Bado unahitaji msaada?' }}
            </p>
            <flux:button href="{{ route('contact') }}" class="mt-4" variant="outline">
                {{ $lang === 'en' ? 'Contact support' : 'Wasiliana na msaada' }}
            </flux:button>
        </div>
    </div>
</div>
