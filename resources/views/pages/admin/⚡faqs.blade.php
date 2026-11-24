<?php

use App\Models\Faq;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts.app')]
#[Title('Admin: FAQs')]
class extends Component {
    public ?int $editingId = null;

    public string $category = Faq::CATEGORY_GENERAL;

    public string $questionEn = '';

    public string $answerEn = '';

    public string $questionSw = '';

    public string $answerSw = '';

    public bool $isPublished = true;

    public function with(): array
    {
        return [
            'faqs' => Faq::query()->ordered()->get(),
            'categories' => Faq::categories(),
        ];
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->editingId = 0;
    }

    public function edit(int $faqId): void
    {
        $faq = Faq::query()->findOrFail($faqId);
        $this->editingId = $faq->id;
        $this->category = $faq->category;
        $this->questionEn = $faq->question_en;
        $this->answerEn = $faq->answer_en;
        $this->questionSw = (string) $faq->question_sw;
        $this->answerSw = (string) $faq->answer_sw;
        $this->isPublished = $faq->is_published;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'category' => ['required', 'in:'.implode(',', array_keys(Faq::categories()))],
            'questionEn' => ['required', 'string', 'max:255'],
            'answerEn' => ['required', 'string', 'max:5000'],
            'questionSw' => ['nullable', 'string', 'max:255'],
            'answerSw' => ['nullable', 'string', 'max:5000'],
            'isPublished' => ['boolean'],
        ]);

        $payload = [
            'category' => $validated['category'],
            'question_en' => $validated['questionEn'],
            'answer_en' => $validated['answerEn'],
            'question_sw' => $validated['questionSw'] ?: null,
            'answer_sw' => $validated['answerSw'] ?: null,
            'is_published' => $validated['isPublished'],
        ];

        if ($this->editingId) {
            Faq::query()->findOrFail($this->editingId)->update($payload);
            Flux::toast(variant: 'success', text: 'FAQ updated.');
        } else {
            $payload['sort_order'] = (int) Faq::query()->max('sort_order') + 1;
            Faq::query()->create($payload);
            Flux::toast(variant: 'success', text: 'FAQ created.');
        }

        $this->resetForm();
    }

    public function moveUp(int $faqId): void
    {
        $this->reorder($faqId, -1);
    }

    public function moveDown(int $faqId): void
    {
        $this->reorder($faqId, 1);
    }

    public function togglePublished(int $faqId): void
    {
        $faq = Faq::query()->findOrFail($faqId);
        $faq->update(['is_published' => ! $faq->is_published]);
        Flux::toast(variant: 'success', text: $faq->is_published ? 'FAQ published.' : 'FAQ hidden.');
    }

    public function delete(int $faqId): void
    {
        Faq::query()->findOrFail($faqId)->delete();
        if ($this->editingId === $faqId) {
            $this->resetForm();
        }
        Flux::toast(variant: 'success', text: 'FAQ deleted.');
    }

    public function cancelEdit(): void
    {
        $this->resetForm();
    }

    private function reorder(int $faqId, int $direction): void
    {
        $items = Faq::query()->ordered()->get()->values();
        $index = $items->search(fn (Faq $item): bool => $item->id === $faqId);

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

    private function resetForm(): void
    {
        $this->reset(['editingId', 'questionEn', 'answerEn', 'questionSw', 'answerSw']);
        $this->category = Faq::CATEGORY_GENERAL;
        $this->isPublished = true;
    }
}; ?>

<div class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">FAQs</flux:heading>
            <flux:text class="mt-1">Questions and answers for the public FAQs page. English is required; Kiswahili is optional.</flux:text>
        </div>
        @unless ($editingId === 0)
            <flux:button wire:click="openCreate">Add FAQ</flux:button>
        @endunless
    </div>

    @if ($editingId !== null)
        <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading size="lg">{{ $editingId ? 'Edit FAQ' : 'New FAQ' }}</flux:heading>
            <div class="mt-4 grid gap-4">
                <flux:select wire:model="category" label="Category">
                    @foreach ($categories as $value => $label)
                        <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="questionEn" label="Question (English)" />
                <flux:textarea wire:model="answerEn" label="Answer (English)" rows="4" />
                <flux:input wire:model="questionSw" label="Question (Kiswahili)" />
                <flux:textarea wire:model="answerSw" label="Answer (Kiswahili)" rows="4" />
                <flux:switch wire:model="isPublished" label="Published on site" />
            </div>
            <div class="mt-4 flex flex-wrap gap-2">
                <flux:button wire:click="save">{{ $editingId ? 'Save changes' : 'Create FAQ' }}</flux:button>
                <flux:button variant="ghost" wire:click="cancelEdit">Cancel</flux:button>
            </div>
        </section>
    @endif

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
        <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
            <thead class="bg-zinc-50 text-left dark:bg-zinc-800">
                <tr>
                    <th class="px-4 py-3 font-medium">Question</th>
                    <th class="px-4 py-3 font-medium">Category</th>
                    <th class="px-4 py-3 font-medium">Status</th>
                    <th class="px-4 py-3 font-medium"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($faqs as $faq)
                    <tr wire:key="faq-{{ $faq->id }}">
                        <td class="px-4 py-3">
                            <div class="font-medium">{{ $faq->question_en }}</div>
                            @if ($faq->question_sw)
                                <div class="mt-1 text-xs text-zinc-500">{{ $faq->question_sw }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3">{{ $faq->categoryLabel() }}</td>
                        <td class="px-4 py-3">
                            <flux:badge size="sm" :color="$faq->is_published ? 'green' : 'zinc'">
                                {{ $faq->is_published ? 'Published' : 'Hidden' }}
                            </flux:badge>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap justify-end gap-2">
                                <flux:button size="sm" variant="outline" wire:click="moveUp({{ $faq->id }})">Up</flux:button>
                                <flux:button size="sm" variant="outline" wire:click="moveDown({{ $faq->id }})">Down</flux:button>
                                <flux:button size="sm" variant="outline" wire:click="edit({{ $faq->id }})">Edit</flux:button>
                                <flux:button size="sm" variant="outline" wire:click="togglePublished({{ $faq->id }})">
                                    {{ $faq->is_published ? 'Hide' : 'Publish' }}
                                </flux:button>
                                <flux:button size="sm" variant="danger" wire:click="delete({{ $faq->id }})" wire:confirm="Delete this FAQ?">
                                    Delete
                                </flux:button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-8 text-center text-zinc-500">No FAQs yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
