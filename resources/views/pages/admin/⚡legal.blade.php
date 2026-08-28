<?php

use App\Models\LegalClause;
use App\Models\LegalSection;
use App\Models\LegalSetting;
use Flux\Flux;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts.app')]
#[Title('Admin: Legal')]
class extends Component {
    public string $version = '';

    public string $effectiveDate = '';

    public ?int $selectedSectionId = null;

    public string $sectionTitle = '';

    public ?int $editingSectionId = null;

    public ?int $editingClauseId = null;

    public string $clauseReference = '';

    public string $clauseTitle = '';

    public string $clauseBody = '';

    public function mount(): void
    {
        $settings = LegalSetting::current();
        $this->version = $settings->version;
        $this->effectiveDate = $settings->effective_date;
        $this->selectedSectionId = LegalSection::query()->ordered()->value('id');
    }

    public function with(): array
    {
        return [
            'sections' => LegalSection::query()->ordered()->withCount('clauses')->get(),
            'clauses' => $this->selectedSectionId
                ? LegalClause::query()
                    ->where('legal_section_id', $this->selectedSectionId)
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->get()
                : collect(),
            'selectedSection' => $this->selectedSectionId
                ? LegalSection::query()->find($this->selectedSectionId)
                : null,
        ];
    }

    public function saveSettings(): void
    {
        $validated = $this->validate([
            'version' => ['required', 'string', 'max:40'],
            'effectiveDate' => ['required', 'string', 'max:80'],
        ]);

        LegalSetting::current()->update([
            'version' => $validated['version'],
            'effective_date' => $validated['effectiveDate'],
        ]);

        Flux::toast(variant: 'success', text: 'Legal page header updated.');
    }

    public function selectSection(int $sectionId): void
    {
        $this->selectedSectionId = $sectionId;
        $this->resetClauseForm();
        $this->editingSectionId = null;
    }

    public function editSection(int $sectionId): void
    {
        $section = LegalSection::query()->findOrFail($sectionId);
        $this->selectedSectionId = $section->id;
        $this->editingSectionId = $section->id;
        $this->sectionTitle = $section->title;
        $this->resetClauseForm();
    }

    public function saveSection(): void
    {
        $validated = $this->validate([
            'sectionTitle' => ['required', 'string', 'max:160'],
        ]);

        if ($this->editingSectionId) {
            $section = LegalSection::query()->findOrFail($this->editingSectionId);
            $section->update([
                'title' => $validated['sectionTitle'],
                'slug' => Str::slug($validated['sectionTitle']),
            ]);
            Flux::toast(variant: 'success', text: 'Legal section updated.');
        } else {
            $section = LegalSection::query()->create([
                'title' => $validated['sectionTitle'],
                'slug' => Str::slug($validated['sectionTitle']).'-'.Str::lower(Str::random(4)),
                'sort_order' => (int) LegalSection::query()->max('sort_order') + 1,
                'is_published' => true,
            ]);
            $this->selectedSectionId = $section->id;
            Flux::toast(variant: 'success', text: 'Legal section created.');
        }

        $this->editingSectionId = null;
        $this->sectionTitle = '';
    }

    public function openCreateSection(): void
    {
        $this->editingSectionId = 0;
        $this->sectionTitle = '';
        $this->resetClauseForm();
    }

    public function toggleSection(int $sectionId): void
    {
        $section = LegalSection::query()->findOrFail($sectionId);
        $section->update(['is_published' => ! $section->is_published]);
        Flux::toast(variant: 'success', text: $section->is_published ? 'Section published.' : 'Section hidden.');
    }

    public function openCreateClause(): void
    {
        $this->resetClauseForm();
        $this->editingClauseId = 0;
    }

    public function editClause(int $clauseId): void
    {
        $clause = LegalClause::query()->findOrFail($clauseId);
        $this->editingClauseId = $clause->id;
        $this->clauseReference = $clause->reference;
        $this->clauseTitle = $clause->title;
        $this->clauseBody = $clause->body;
    }

    public function saveClause(): void
    {
        $validated = $this->validate([
            'clauseReference' => ['required', 'string', 'max:120'],
            'clauseTitle' => ['required', 'string', 'max:200'],
            'clauseBody' => ['required', 'string', 'max:10000'],
        ]);

        if (! $this->selectedSectionId) {
            Flux::toast(variant: 'danger', text: 'Select a legal section first.');

            return;
        }

        $payload = [
            'legal_section_id' => $this->selectedSectionId,
            'reference' => $validated['clauseReference'],
            'title' => $validated['clauseTitle'],
            'body' => $validated['clauseBody'],
        ];

        if ($this->editingClauseId) {
            LegalClause::query()->findOrFail($this->editingClauseId)->update($payload);
            Flux::toast(variant: 'success', text: 'Clause updated.');
        } else {
            $payload['sort_order'] = (int) LegalClause::query()
                ->where('legal_section_id', $this->selectedSectionId)
                ->max('sort_order') + 1;
            LegalClause::query()->create($payload);
            Flux::toast(variant: 'success', text: 'Clause created.');
        }

        $this->resetClauseForm();
    }

    public function moveClauseUp(int $clauseId): void
    {
        $this->reorderClause($clauseId, -1);
    }

    public function moveClauseDown(int $clauseId): void
    {
        $this->reorderClause($clauseId, 1);
    }

    public function deleteClause(int $clauseId): void
    {
        LegalClause::query()->findOrFail($clauseId)->delete();
        if ($this->editingClauseId === $clauseId) {
            $this->resetClauseForm();
        }
        Flux::toast(variant: 'success', text: 'Clause deleted.');
    }

    public function cancelSectionEdit(): void
    {
        $this->editingSectionId = null;
        $this->sectionTitle = '';
    }

    public function cancelClauseEdit(): void
    {
        $this->resetClauseForm();
    }

    private function reorderClause(int $clauseId, int $direction): void
    {
        $items = LegalClause::query()
            ->where('legal_section_id', $this->selectedSectionId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->values();

        $index = $items->search(fn (LegalClause $item): bool => $item->id === $clauseId);

        if ($index === false) {
            return;
        }

        $swapIndex = $index + $direction;

        if ($swapIndex < 0 || $swapIndex >= $items->count()) {
            return;
        }

        $current = $items[$index];
        $other = $items[$swapIndex];
        $currentOrder = $current->sort_order;
        $current->update(['sort_order' => $other->sort_order]);
        $other->update(['sort_order' => $currentOrder]);
    }

    private function resetClauseForm(): void
    {
        $this->reset(['editingClauseId', 'clauseReference', 'clauseTitle', 'clauseBody']);
    }
}; ?>

<div class="space-y-6">
    <div>
        <flux:heading size="xl">Legal</flux:heading>
        <flux:text class="mt-1">Edit the public Legal & Compliance page: header details, sections, and clause text.</flux:text>
    </div>

    <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:heading size="lg">Page header</flux:heading>
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <flux:input wire:model="version" label="Version" />
            <flux:input wire:model="effectiveDate" label="Effective date" />
        </div>
        <flux:button class="mt-4" wire:click="saveSettings">Save header</flux:button>
    </section>

    <div class="grid gap-6 lg:grid-cols-[16rem_minmax(0,1fr)]">
        <aside class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-sm font-semibold">Sections</h2>
                <flux:button size="sm" variant="outline" wire:click="openCreateSection">Add</flux:button>
            </div>
            @if ($editingSectionId !== null)
                <div class="mt-3 space-y-2">
                    <flux:input wire:model="sectionTitle" label="Section title" />
                    <div class="flex gap-2">
                        <flux:button size="sm" wire:click="saveSection">Save</flux:button>
                        <flux:button size="sm" variant="ghost" wire:click="cancelSectionEdit">Cancel</flux:button>
                    </div>
                </div>
            @endif
            <ul class="mt-4 space-y-1">
                @foreach ($sections as $section)
                    <li wire:key="legal-section-{{ $section->id }}">
                        <button
                            type="button"
                            wire:click="selectSection({{ $section->id }})"
                            @class([
                                'flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-sm',
                                'bg-navy text-white' => $selectedSectionId === $section->id,
                                'hover:bg-zinc-100 dark:hover:bg-zinc-800' => $selectedSectionId !== $section->id,
                            ])
                        >
                            <span class="truncate">{{ $section->title }}</span>
                            <span class="text-xs opacity-70">{{ $section->clauses_count }}</span>
                        </button>
                    </li>
                @endforeach
            </ul>
        </aside>

        <div class="space-y-4">
            @if ($selectedSection)
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <flux:heading size="lg">{{ $selectedSection->title }}</flux:heading>
                        <flux:text class="mt-1">Slug: {{ $selectedSection->slug }}</flux:text>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <flux:button size="sm" variant="outline" wire:click="editSection({{ $selectedSection->id }})">Rename section</flux:button>
                        <flux:button size="sm" variant="outline" wire:click="toggleSection({{ $selectedSection->id }})">
                            {{ $selectedSection->is_published ? 'Hide section' : 'Publish section' }}
                        </flux:button>
                        <flux:button size="sm" wire:click="openCreateClause">Add clause</flux:button>
                    </div>
                </div>

                @if ($editingClauseId !== null)
                    <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                        <flux:heading size="lg">{{ $editingClauseId ? 'Edit clause' : 'New clause' }}</flux:heading>
                        <div class="mt-4 grid gap-4">
                            <flux:input wire:model="clauseReference" label="Reference" placeholder="1.1 Parties and acceptance" />
                            <flux:input wire:model="clauseTitle" label="Heading" />
                            <flux:textarea wire:model="clauseBody" label="Body" rows="6" />
                        </div>
                        <div class="mt-4 flex gap-2">
                            <flux:button wire:click="saveClause">{{ $editingClauseId ? 'Save clause' : 'Create clause' }}</flux:button>
                            <flux:button variant="ghost" wire:click="cancelClauseEdit">Cancel</flux:button>
                        </div>
                    </section>
                @endif

                <div class="space-y-3">
                    @forelse ($clauses as $clause)
                        <article wire:key="legal-clause-{{ $clause->id }}" class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ $clause->reference }}</div>
                                    <h3 class="mt-1 font-semibold">{{ $clause->title }}</h3>
                                    <p class="mt-2 text-sm text-zinc-700 dark:text-zinc-300">{{ $clause->body }}</p>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <flux:button size="sm" variant="outline" wire:click="moveClauseUp({{ $clause->id }})">Up</flux:button>
                                    <flux:button size="sm" variant="outline" wire:click="moveClauseDown({{ $clause->id }})">Down</flux:button>
                                    <flux:button size="sm" variant="outline" wire:click="editClause({{ $clause->id }})">Edit</flux:button>
                                    <flux:button size="sm" variant="danger" wire:click="deleteClause({{ $clause->id }})" wire:confirm="Delete this clause?">
                                        Delete
                                    </flux:button>
                                </div>
                            </div>
                        </article>
                    @empty
                        <div class="rounded-xl border border-dashed border-zinc-300 p-8 text-center text-sm text-zinc-500 dark:border-zinc-600">
                            No clauses in this section yet.
                        </div>
                    @endforelse
                </div>
            @else
                <div class="rounded-xl border border-dashed border-zinc-300 p-8 text-center text-sm text-zinc-500 dark:border-zinc-600">
                    Select a legal section to manage its clauses.
                </div>
            @endif
        </div>
    </div>
</div>
