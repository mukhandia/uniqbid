<?php

use App\Models\LegalSection;
use App\Models\LegalSetting;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts.site')]
#[Title('Legal & Compliance: UniqBid')]
class extends Component {
    public function with(): array
    {
        return [
            'settings' => LegalSetting::current(),
            'sections' => LegalSection::query()
                ->published()
                ->ordered()
                ->with('clauses')
                ->get(),
        ];
    }
}; ?>

<div class="mx-auto max-w-4xl px-4 py-10">
    <div class="flex items-center gap-3">
        <flux:icon.document-text class="size-6 text-navy" />
        <h1 class="text-3xl font-bold text-navy">Legal & Compliance</h1>
    </div>
    <div class="mt-2 flex flex-wrap items-center gap-3 text-xs text-muted-foreground">
        <span>Version {{ $settings->version }}</span>
        <span>·</span>
        <span>Effective {{ $settings->effective_date }}</span>
        <span>·</span>
        <span class="inline-flex items-center gap-1">
            <flux:icon.shield-check class="size-3.5 text-success" />
            Kenya Data Protection Act, 2019
        </span>
    </div>

    <div class="mt-6 rounded-2xl border border-border bg-white p-4">
        <div class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Contents</div>
        <ul class="mt-2 grid gap-1 sm:grid-cols-2">
            @foreach ($sections as $section)
                <li wire:key="legal-nav-{{ $section->id }}">
                    <a href="#{{ $section->slug }}" class="text-sm text-navy hover:underline">{{ $section->title }}</a>
                </li>
            @endforeach
        </ul>
    </div>

    <div class="mt-4 flex items-start gap-3 rounded-2xl border border-border bg-muted p-4 text-sm text-zinc-800">
        <flux:icon.document-arrow-down class="mt-0.5 size-5 shrink-0 text-navy" />
        <p>
            Read the complete published framework, including the full clause text:
            <a href="/documents/uniqbid-legal-compliance-framework.pdf" target="_blank" rel="noopener" class="font-semibold text-navy underline underline-offset-2">
                Download the Legal & Compliance Framework (PDF)
            </a>.
        </p>
    </div>

    <div class="mt-8 space-y-8">
        @foreach ($sections as $section)
            <section id="{{ $section->slug }}" class="scroll-mt-24" wire:key="legal-section-{{ $section->id }}">
                <h2 class="text-xl font-bold text-navy">{{ $section->title }}</h2>
                <div class="mt-3 space-y-4 text-sm leading-relaxed text-zinc-800">
                    @foreach ($section->clauses as $clause)
                        <div wire:key="legal-clause-{{ $clause->id }}">
                            <h3 class="font-semibold text-zinc-900">{{ $clause->title }}</h3>
                            <p class="mt-1">{{ $clause->body }}</p>
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
</div>
