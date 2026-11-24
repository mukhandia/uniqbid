<?php

use App\Models\HowItWorksExampleRow;
use App\Models\HowItWorksSetting;
use App\Models\HowItWorksStep;
use App\Support\Money;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts.app')]
#[Title('Admin: How it works')]
class extends Component {
    public string $lang = 'en';

    public string $pageTitleEn = '';

    public string $pageTitleSw = '';

    public string $introEn = '';

    public string $introSw = '';

    public string $exampleHeadingEn = '';

    public string $exampleHeadingSw = '';

    public string $exampleIntroEn = '';

    public string $exampleIntroSw = '';

    public string $exampleNoteEn = '';

    public string $exampleNoteSw = '';

    public string $whatYouPayTitleEn = '';

    public string $whatYouPayTitleSw = '';

    public string $whatYouPayBodyEn = '';

    public string $whatYouPayBodySw = '';

    public string $transparencyTitleEn = '';

    public string $transparencyTitleSw = '';

    public string $transparencyBodyEn = '';

    public string $transparencyBodySw = '';

    public ?int $editingStepId = null;

    public int $stepNumber = 1;

    public string $stepTitleEn = '';

    public string $stepTitleSw = '';

    public string $stepDescriptionEn = '';

    public string $stepDescriptionSw = '';

    public ?int $editingRowId = null;

    public string $rowName = '';

    public int $rowAmount = 0;

    public bool $rowIsUnique = true;

    public function mount(): void
    {
        $this->loadSettings();
    }

    public function with(): array
    {
        return [
            'steps' => HowItWorksStep::query()->orderBy('sort_order')->orderBy('id')->get(),
            'exampleRows' => HowItWorksExampleRow::query()->orderBy('sort_order')->orderBy('id')->get(),
        ];
    }

    public function saveSettings(): void
    {
        $validated = $this->validate([
            'pageTitleEn' => ['required', 'string', 'max:160'],
            'pageTitleSw' => ['required', 'string', 'max:160'],
            'introEn' => ['required', 'string', 'max:2000'],
            'introSw' => ['required', 'string', 'max:2000'],
            'exampleHeadingEn' => ['required', 'string', 'max:160'],
            'exampleHeadingSw' => ['required', 'string', 'max:160'],
            'exampleIntroEn' => ['required', 'string', 'max:500'],
            'exampleIntroSw' => ['required', 'string', 'max:500'],
            'exampleNoteEn' => ['required', 'string', 'max:2000'],
            'exampleNoteSw' => ['required', 'string', 'max:2000'],
            'whatYouPayTitleEn' => ['required', 'string', 'max:160'],
            'whatYouPayTitleSw' => ['required', 'string', 'max:160'],
            'whatYouPayBodyEn' => ['required', 'string', 'max:5000'],
            'whatYouPayBodySw' => ['required', 'string', 'max:5000'],
            'transparencyTitleEn' => ['required', 'string', 'max:160'],
            'transparencyTitleSw' => ['required', 'string', 'max:160'],
            'transparencyBodyEn' => ['required', 'string', 'max:2000'],
            'transparencyBodySw' => ['required', 'string', 'max:2000'],
        ]);

        HowItWorksSetting::current()->update([
            'page_title_en' => $validated['pageTitleEn'],
            'page_title_sw' => $validated['pageTitleSw'],
            'intro_en' => $validated['introEn'],
            'intro_sw' => $validated['introSw'],
            'example_heading_en' => $validated['exampleHeadingEn'],
            'example_heading_sw' => $validated['exampleHeadingSw'],
            'example_intro_en' => $validated['exampleIntroEn'],
            'example_intro_sw' => $validated['exampleIntroSw'],
            'example_note_en' => $validated['exampleNoteEn'],
            'example_note_sw' => $validated['exampleNoteSw'],
            'what_you_pay_title_en' => $validated['whatYouPayTitleEn'],
            'what_you_pay_title_sw' => $validated['whatYouPayTitleSw'],
            'what_you_pay_body_en' => $validated['whatYouPayBodyEn'],
            'what_you_pay_body_sw' => $validated['whatYouPayBodySw'],
            'transparency_title_en' => $validated['transparencyTitleEn'],
            'transparency_title_sw' => $validated['transparencyTitleSw'],
            'transparency_body_en' => $validated['transparencyBodyEn'],
            'transparency_body_sw' => $validated['transparencyBodySw'],
        ]);

        Flux::toast(variant: 'success', text: 'How it works page updated.');
    }

    public function openCreateStep(): void
    {
        $this->resetStepForm();
        $this->editingStepId = 0;
        $this->stepNumber = (int) HowItWorksStep::query()->max('step_number') + 1;
    }

    public function editStep(int $stepId): void
    {
        $step = HowItWorksStep::query()->findOrFail($stepId);
        $this->editingStepId = $step->id;
        $this->stepNumber = $step->step_number;
        $this->stepTitleEn = $step->title_en;
        $this->stepTitleSw = $step->title_sw;
        $this->stepDescriptionEn = $step->description_en;
        $this->stepDescriptionSw = $step->description_sw;
    }

    public function saveStep(): void
    {
        $validated = $this->validate([
            'stepNumber' => ['required', 'integer', 'min:1', 'max:20'],
            'stepTitleEn' => ['required', 'string', 'max:160'],
            'stepTitleSw' => ['required', 'string', 'max:160'],
            'stepDescriptionEn' => ['required', 'string', 'max:2000'],
            'stepDescriptionSw' => ['required', 'string', 'max:2000'],
        ]);

        $payload = [
            'step_number' => $validated['stepNumber'],
            'title_en' => $validated['stepTitleEn'],
            'title_sw' => $validated['stepTitleSw'],
            'description_en' => $validated['stepDescriptionEn'],
            'description_sw' => $validated['stepDescriptionSw'],
        ];

        if ($this->editingStepId) {
            HowItWorksStep::query()->findOrFail($this->editingStepId)->update($payload);
            Flux::toast(variant: 'success', text: 'Step updated.');
        } else {
            $payload['sort_order'] = (int) HowItWorksStep::query()->max('sort_order') + 1;
            HowItWorksStep::query()->create($payload);
            Flux::toast(variant: 'success', text: 'Step created.');
        }

        $this->resetStepForm();
    }

    public function deleteStep(int $stepId): void
    {
        HowItWorksStep::query()->findOrFail($stepId)->delete();
        if ($this->editingStepId === $stepId) {
            $this->resetStepForm();
        }
        Flux::toast(variant: 'success', text: 'Step deleted.');
    }

    public function openCreateRow(): void
    {
        $this->resetRowForm();
        $this->editingRowId = 0;
    }

    public function editRow(int $rowId): void
    {
        $row = HowItWorksExampleRow::query()->findOrFail($rowId);
        $this->editingRowId = $row->id;
        $this->rowName = $row->name;
        $this->rowAmount = $row->amount;
        $this->rowIsUnique = $row->is_unique;
    }

    public function saveRow(): void
    {
        $validated = $this->validate([
            'rowName' => ['required', 'string', 'max:80'],
            'rowAmount' => ['required', 'integer', 'min:1'],
            'rowIsUnique' => ['boolean'],
        ]);

        $payload = [
            'name' => $validated['rowName'],
            'amount' => $validated['rowAmount'],
            'is_unique' => $validated['rowIsUnique'],
        ];

        if ($this->editingRowId) {
            HowItWorksExampleRow::query()->findOrFail($this->editingRowId)->update($payload);
            Flux::toast(variant: 'success', text: 'Example row updated.');
        } else {
            $payload['sort_order'] = (int) HowItWorksExampleRow::query()->max('sort_order') + 1;
            HowItWorksExampleRow::query()->create($payload);
            Flux::toast(variant: 'success', text: 'Example row created.');
        }

        $this->resetRowForm();
    }

    public function deleteRow(int $rowId): void
    {
        HowItWorksExampleRow::query()->findOrFail($rowId)->delete();
        if ($this->editingRowId === $rowId) {
            $this->resetRowForm();
        }
        Flux::toast(variant: 'success', text: 'Example row deleted.');
    }

    public function cancelStepEdit(): void
    {
        $this->resetStepForm();
    }

    public function cancelRowEdit(): void
    {
        $this->resetRowForm();
    }

    private function loadSettings(): void
    {
        $settings = HowItWorksSetting::current();
        $this->pageTitleEn = $settings->page_title_en;
        $this->pageTitleSw = $settings->page_title_sw;
        $this->introEn = $settings->intro_en;
        $this->introSw = $settings->intro_sw;
        $this->exampleHeadingEn = $settings->example_heading_en;
        $this->exampleHeadingSw = $settings->example_heading_sw;
        $this->exampleIntroEn = $settings->example_intro_en;
        $this->exampleIntroSw = $settings->example_intro_sw;
        $this->exampleNoteEn = $settings->example_note_en;
        $this->exampleNoteSw = $settings->example_note_sw;
        $this->whatYouPayTitleEn = $settings->what_you_pay_title_en;
        $this->whatYouPayTitleSw = $settings->what_you_pay_title_sw;
        $this->whatYouPayBodyEn = $settings->what_you_pay_body_en;
        $this->whatYouPayBodySw = $settings->what_you_pay_body_sw;
        $this->transparencyTitleEn = $settings->transparency_title_en;
        $this->transparencyTitleSw = $settings->transparency_title_sw;
        $this->transparencyBodyEn = $settings->transparency_body_en;
        $this->transparencyBodySw = $settings->transparency_body_sw;
    }

    private function resetStepForm(): void
    {
        $this->reset(['editingStepId', 'stepTitleEn', 'stepTitleSw', 'stepDescriptionEn', 'stepDescriptionSw']);
        $this->stepNumber = 1;
    }

    private function resetRowForm(): void
    {
        $this->reset(['editingRowId', 'rowName', 'rowAmount']);
        $this->rowIsUnique = true;
        $this->rowAmount = 0;
    }
}; ?>

<div class="space-y-6">
    <div>
        <flux:heading size="xl">How it works</flux:heading>
        <flux:text class="mt-1">Edit the bilingual How it works page, steps, worked example, and info blocks.</flux:text>
    </div>

    <div class="inline-flex rounded-md border border-zinc-200 bg-white p-1 text-xs dark:border-zinc-700 dark:bg-zinc-900">
        <button type="button" wire:click="$set('lang', 'en')" @class(['rounded px-3 py-1.5', 'bg-navy text-white' => $lang === 'en'])>English</button>
        <button type="button" wire:click="$set('lang', 'sw')" @class(['rounded px-3 py-1.5', 'bg-navy text-white' => $lang === 'sw'])>Kiswahili</button>
    </div>

    <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:heading size="lg">Page copy</flux:heading>
        <div class="mt-4 grid gap-4">
            @if ($lang === 'en')
                <flux:input wire:model="pageTitleEn" label="Page title" />
                <flux:textarea wire:model="introEn" label="Intro" rows="3" />
                <flux:input wire:model="exampleHeadingEn" label="Worked example heading" />
                <flux:input wire:model="exampleIntroEn" label="Worked example intro" />
                <flux:textarea wire:model="exampleNoteEn" label="Worked example note" rows="3" helper="Use {amount} where the successful bid amount should appear." />
                <flux:input wire:model="whatYouPayTitleEn" label="What you pay title" />
                <flux:textarea wire:model="whatYouPayBodyEn" label="What you pay body" rows="4" helper="Basic HTML allowed (strong, br)." />
                <flux:input wire:model="transparencyTitleEn" label="Transparency title" />
                <flux:textarea wire:model="transparencyBodyEn" label="Transparency body" rows="3" />
            @else
                <flux:input wire:model="pageTitleSw" label="Page title" />
                <flux:textarea wire:model="introSw" label="Intro" rows="3" />
                <flux:input wire:model="exampleHeadingSw" label="Worked example heading" />
                <flux:input wire:model="exampleIntroSw" label="Worked example intro" />
                <flux:textarea wire:model="exampleNoteSw" label="Worked example note" rows="3" helper="Use {amount} where the successful bid amount should appear." />
                <flux:input wire:model="whatYouPayTitleSw" label="What you pay title" />
                <flux:textarea wire:model="whatYouPayBodySw" label="What you pay body" rows="4" helper="Basic HTML allowed (strong, br)." />
                <flux:input wire:model="transparencyTitleSw" label="Transparency title" />
                <flux:textarea wire:model="transparencyBodySw" label="Transparency body" rows="3" />
            @endif
        </div>
        <flux:button class="mt-4" wire:click="saveSettings">Save page copy</flux:button>
    </section>

    <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <flux:heading size="lg">Steps</flux:heading>
            @unless ($editingStepId === 0)
                <flux:button size="sm" wire:click="openCreateStep">Add step</flux:button>
            @endunless
        </div>

        @if ($editingStepId !== null)
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="stepNumber" type="number" label="Step number" min="1" />
                <flux:input wire:model="stepTitleEn" label="Title (English)" />
                <flux:input wire:model="stepTitleSw" label="Title (Kiswahili)" />
                <div class="sm:col-span-2">
                    <flux:textarea wire:model="stepDescriptionEn" label="Description (English)" rows="3" />
                </div>
                <div class="sm:col-span-2">
                    <flux:textarea wire:model="stepDescriptionSw" label="Description (Kiswahili)" rows="3" />
                </div>
            </div>
            <div class="mt-4 flex gap-2">
                <flux:button wire:click="saveStep">{{ $editingStepId ? 'Save step' : 'Create step' }}</flux:button>
                <flux:button variant="ghost" wire:click="cancelStepEdit">Cancel</flux:button>
            </div>
        @endif

        <div class="mt-4 space-y-2">
            @foreach ($steps as $step)
                <div wire:key="step-{{ $step->id }}" class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-zinc-200 px-4 py-3 dark:border-zinc-700">
                    <div>
                        <div class="font-medium">{{ $step->step_number }}. {{ $step->title_en }}</div>
                        <div class="text-xs text-zinc-500">{{ $step->title_sw }}</div>
                    </div>
                    <div class="flex gap-2">
                        <flux:button size="sm" variant="outline" wire:click="editStep({{ $step->id }})">Edit</flux:button>
                        <flux:button size="sm" variant="danger" wire:click="deleteStep({{ $step->id }})" wire:confirm="Delete this step?">Delete</flux:button>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <flux:heading size="lg">Worked example rows</flux:heading>
            @unless ($editingRowId === 0)
                <flux:button size="sm" wire:click="openCreateRow">Add row</flux:button>
            @endunless
        </div>

        @if ($editingRowId !== null)
            <div class="mt-4 grid gap-4 sm:grid-cols-3">
                <flux:input wire:model="rowName" label="Bidder name" />
                <flux:input wire:model="rowAmount" type="number" label="Bid amount (KES)" min="1" />
                <flux:switch wire:model="rowIsUnique" label="Unique amount" />
            </div>
            <div class="mt-4 flex gap-2">
                <flux:button wire:click="saveRow">{{ $editingRowId ? 'Save row' : 'Create row' }}</flux:button>
                <flux:button variant="ghost" wire:click="cancelRowEdit">Cancel</flux:button>
            </div>
        @endif

        <div class="mt-4 space-y-2">
            @foreach ($exampleRows as $row)
                <div wire:key="example-row-{{ $row->id }}" class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-zinc-200 px-4 py-3 dark:border-zinc-700">
                    <div class="font-medium">{{ $row->name }} · {{ Money::kes($row->amount) }} · {{ $row->is_unique ? 'Unique' : 'Tied' }}</div>
                    <div class="flex gap-2">
                        <flux:button size="sm" variant="outline" wire:click="editRow({{ $row->id }})">Edit</flux:button>
                        <flux:button size="sm" variant="danger" wire:click="deleteRow({{ $row->id }})" wire:confirm="Delete this row?">Delete</flux:button>
                    </div>
                </div>
            @endforeach
        </div>
    </section>
</div>
