<?php

use App\Models\Testimonial;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts.app')]
#[Title('Admin: Testimonials')]
class extends Component {
    public ?int $editingId = null;

    public string $name = '';

    public string $location = '';

    public string $role = '';

    public string $item = '';

    public string $quote = '';

    public int $rating = 5;

    public bool $isPublished = true;

    public function with(): array
    {
        return [
            'testimonials' => Testimonial::query()->ordered()->get(),
        ];
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->editingId = 0;
    }

    public function edit(int $testimonialId): void
    {
        $testimonial = Testimonial::query()->findOrFail($testimonialId);
        $this->editingId = $testimonial->id;
        $this->name = $testimonial->name;
        $this->location = $testimonial->location;
        $this->role = $testimonial->role;
        $this->item = $testimonial->item;
        $this->quote = $testimonial->quote;
        $this->rating = $testimonial->rating;
        $this->isPublished = $testimonial->is_published;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'location' => ['required', 'string', 'max:120'],
            'role' => ['required', 'string', 'max:120'],
            'item' => ['required', 'string', 'max:160'],
            'quote' => ['required', 'string', 'max:2000'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'isPublished' => ['boolean'],
        ]);

        $payload = [
            'name' => $validated['name'],
            'location' => $validated['location'],
            'role' => $validated['role'],
            'item' => $validated['item'],
            'quote' => $validated['quote'],
            'rating' => $validated['rating'],
            'is_published' => $validated['isPublished'],
        ];

        if ($this->editingId) {
            Testimonial::query()->findOrFail($this->editingId)->update($payload);
            Flux::toast(variant: 'success', text: 'Testimonial updated.');
        } else {
            $payload['sort_order'] = (int) Testimonial::query()->max('sort_order') + 1;
            Testimonial::query()->create($payload);
            Flux::toast(variant: 'success', text: 'Testimonial created.');
        }

        $this->resetForm();
    }

    public function moveUp(int $testimonialId): void
    {
        $this->reorder($testimonialId, -1);
    }

    public function moveDown(int $testimonialId): void
    {
        $this->reorder($testimonialId, 1);
    }

    public function togglePublished(int $testimonialId): void
    {
        $testimonial = Testimonial::query()->findOrFail($testimonialId);
        $testimonial->update(['is_published' => ! $testimonial->is_published]);
        Flux::toast(variant: 'success', text: $testimonial->is_published ? 'Testimonial published.' : 'Testimonial hidden.');
    }

    public function delete(int $testimonialId): void
    {
        Testimonial::query()->findOrFail($testimonialId)->delete();
        if ($this->editingId === $testimonialId) {
            $this->resetForm();
        }
        Flux::toast(variant: 'success', text: 'Testimonial deleted.');
    }

    public function cancelEdit(): void
    {
        $this->resetForm();
    }

    private function reorder(int $testimonialId, int $direction): void
    {
        $items = Testimonial::query()->ordered()->get()->values();
        $index = $items->search(fn (Testimonial $item): bool => $item->id === $testimonialId);

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
        $this->reset(['editingId', 'name', 'location', 'role', 'item', 'quote']);
        $this->rating = 5;
        $this->isPublished = true;
    }
}; ?>

<div class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">Testimonials</flux:heading>
            <flux:text class="mt-1">Manage customer stories shown on the public testimonials page.</flux:text>
        </div>
        @unless ($editingId === 0)
            <flux:button wire:click="openCreate">Add testimonial</flux:button>
        @endunless
    </div>

    @if ($editingId !== null)
        <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading size="lg">{{ $editingId ? 'Edit testimonial' : 'New testimonial' }}</flux:heading>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="name" label="Name" />
                <flux:input wire:model="location" label="Location" />
                <flux:input wire:model="role" label="Role" placeholder="Successful buyer" />
                <flux:input wire:model="item" label="Item" />
                <div class="sm:col-span-2">
                    <flux:textarea wire:model="quote" label="Quote" rows="4" />
                </div>
                <flux:select wire:model="rating" label="Rating">
                    @foreach (range(1, 5) as $stars)
                        <flux:select.option value="{{ $stars }}">{{ $stars }} star{{ $stars === 1 ? '' : 's' }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:switch wire:model="isPublished" label="Published on site" />
            </div>
            <div class="mt-4 flex flex-wrap gap-2">
                <flux:button wire:click="save">{{ $editingId ? 'Save changes' : 'Create testimonial' }}</flux:button>
                <flux:button variant="ghost" wire:click="cancelEdit">Cancel</flux:button>
            </div>
        </section>
    @endif

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
        <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
            <thead class="bg-zinc-50 text-left dark:bg-zinc-800">
                <tr>
                    <th class="px-4 py-3 font-medium">Customer</th>
                    <th class="px-4 py-3 font-medium">Story</th>
                    <th class="px-4 py-3 font-medium">Rating</th>
                    <th class="px-4 py-3 font-medium">Status</th>
                    <th class="px-4 py-3 font-medium"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($testimonials as $testimonial)
                    <tr wire:key="testimonial-{{ $testimonial->id }}">
                        <td class="px-4 py-3">
                            <div class="font-medium">{{ $testimonial->name }}</div>
                            <div class="text-xs text-zinc-500">{{ $testimonial->location }} · {{ $testimonial->role }}</div>
                            <div class="text-xs text-zinc-500">{{ $testimonial->item }}</div>
                        </td>
                        <td class="max-w-md px-4 py-3 text-zinc-600 dark:text-zinc-300">{{ Str::limit($testimonial->quote, 120) }}</td>
                        <td class="px-4 py-3">{{ $testimonial->rating }}/5</td>
                        <td class="px-4 py-3">
                            <flux:badge size="sm" :color="$testimonial->is_published ? 'green' : 'zinc'">
                                {{ $testimonial->is_published ? 'Published' : 'Hidden' }}
                            </flux:badge>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap justify-end gap-2">
                                <flux:button size="sm" variant="outline" wire:click="moveUp({{ $testimonial->id }})">Up</flux:button>
                                <flux:button size="sm" variant="outline" wire:click="moveDown({{ $testimonial->id }})">Down</flux:button>
                                <flux:button size="sm" variant="outline" wire:click="edit({{ $testimonial->id }})">Edit</flux:button>
                                <flux:button size="sm" variant="outline" wire:click="togglePublished({{ $testimonial->id }})">
                                    {{ $testimonial->is_published ? 'Hide' : 'Publish' }}
                                </flux:button>
                                <flux:button size="sm" variant="danger" wire:click="delete({{ $testimonial->id }})" wire:confirm="Delete this testimonial?">
                                    Delete
                                </flux:button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-zinc-500">No testimonials yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
