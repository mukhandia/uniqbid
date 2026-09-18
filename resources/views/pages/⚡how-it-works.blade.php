<?php

use App\Models\HowItWorksExampleRow;
use App\Models\HowItWorksSetting;
use App\Models\HowItWorksStep;
use App\Support\Money;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts.site')]
#[Title('How UniqBid Works: Lowest Unique Amount Sales in Kenya')]
class extends Component {
    public string $lang = 'en';

    public function with(): array
    {
        $settings = HowItWorksSetting::current();
        $exampleRows = HowItWorksExampleRow::query()->orderBy('sort_order')->orderBy('id')->get();
        $successfulAmount = (int) ($exampleRows
            ->where('is_unique', true)
            ->min('amount') ?? 0);

        return [
            'settings' => $settings,
            'steps' => HowItWorksStep::query()->orderBy('sort_order')->orderBy('id')->get(),
            'exampleRows' => $exampleRows,
            'successfulAmount' => $successfulAmount,
        ];
    }

    public function field(string $base): string
    {
        $settings = HowItWorksSetting::current();
        $column = $base.'_'.$this->lang;

        return (string) ($settings->{$column} ?? $settings->{$base.'_en'});
    }

    public function exampleNote(): string
    {
        $settings = HowItWorksSetting::current();
        $template = $this->lang === 'sw'
            ? $settings->example_note_sw
            : $settings->example_note_en;
        $amount = Money::kes($this->successfulAmount());

        return str_replace('{amount}', $amount, $template);
    }

    private function successfulAmount(): int
    {
        return (int) (HowItWorksExampleRow::query()
            ->where('is_unique', true)
            ->min('amount') ?? 0);
    }
}; ?>

<div class="bg-surface">
    <div class="mx-auto max-w-5xl px-4 py-12">
        <div class="flex items-center justify-between gap-4">
            <h1 class="text-3xl font-bold text-navy sm:text-4xl">
                {{ $this->field('page_title') }}
            </h1>
            <div class="inline-flex rounded-md border border-border bg-white p-1 text-xs">
                <button type="button" wire:click="$set('lang', 'en')" @class(['rounded px-2.5 py-1', 'bg-navy text-navy-foreground' => $lang === 'en', 'text-muted-foreground' => $lang !== 'en'])>English</button>
                <button type="button" wire:click="$set('lang', 'sw')" @class(['rounded px-2.5 py-1', 'bg-navy text-navy-foreground' => $lang === 'sw', 'text-muted-foreground' => $lang !== 'sw'])>Kiswahili</button>
            </div>
        </div>

        <p class="mt-3 max-w-2xl text-base text-muted-foreground">
            {{ $this->field('intro') }}
        </p>

        <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($steps as $step)
                <div class="rounded-2xl border border-border bg-white p-6 shadow-sm" wire:key="step-{{ $step->id }}">
                    <div class="grid h-10 w-10 place-items-center rounded-full bg-gold font-bold text-gold-foreground">{{ $step->step_number }}</div>
                    <h3 class="mt-4 text-lg font-semibold text-navy">{{ $step->titleFor($lang) }}</h3>
                    <p class="mt-2 text-sm text-muted-foreground">{{ $step->descriptionFor($lang) }}</p>
                </div>
            @endforeach
        </div>

        <div class="mt-10 rounded-2xl border border-border bg-white p-6 shadow-sm">
            <h2 class="text-xl font-bold text-navy">{{ $this->field('example_heading') }}</h2>
            <p class="mt-1 text-sm text-muted-foreground">{{ $this->field('example_intro') }}</p>
            <div class="mt-4 divide-y divide-border">
                @foreach ($exampleRows as $bidder)
                    @php $isSuccessfulBuy = $bidder->isSuccessfulBuy($successfulAmount); @endphp
                    <div @class(['flex items-center justify-between py-3', 'rounded-md bg-gold/10 px-3' => $isSuccessfulBuy]) wire:key="example-row-{{ $bidder->id }}">
                        <div class="flex items-center gap-3">
                            <div class="grid h-8 w-8 place-items-center rounded-full bg-navy text-xs font-bold text-navy-foreground">{{ substr($bidder->name, -1) }}</div>
                            <span class="text-sm font-medium">{{ $bidder->name }}</span>
                            @unless ($bidder->is_unique)
                                <span class="rounded-full bg-muted px-2 py-0.5 text-[10px] font-semibold text-muted-foreground">tied; not unique</span>
                            @endunless
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="font-mono text-sm font-semibold">{{ Money::kes($bidder->amount) }}</span>
                            @if ($isSuccessfulBuy)
                                <span class="inline-flex items-center gap-1 rounded-full bg-gold px-2.5 py-1 text-xs font-bold text-gold-foreground">
                                    <flux:icon.check-circle class="size-3.5" /> Successful buy
                                </span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
            <p class="mt-4 text-sm text-zinc-800">
                {!! $this->exampleNote() !!}
            </p>
        </div>

        <div class="mt-10 grid gap-4 md:grid-cols-2">
            <div class="rounded-2xl border border-border bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-navy">{{ $this->field('what_you_pay_title') }}</h3>
                <p class="mt-2 text-sm text-muted-foreground">
                    {!! $this->field('what_you_pay_body') !!}
                </p>
            </div>
            <div class="rounded-2xl border border-border bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-navy">{{ $this->field('transparency_title') }}</h3>
                <p class="mt-2 text-sm text-muted-foreground">
                    {{ $this->field('transparency_body') }}
                </p>
            </div>
        </div>

        <div class="mt-10 text-center">
            <flux:button href="{{ route('auctions.index') }}" class="bg-accent! text-accent-foreground!">
                    Browse live sales
            </flux:button>
        </div>
    </div>
</div>
