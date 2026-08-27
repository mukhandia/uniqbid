<?php

use App\Services\SubscriberListService;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new
#[Layout('layouts.app')]
#[Title('Admin: Subscribers')]
class extends Component {
    use WithPagination;

    public function with(): array
    {
        $all = collect(app(SubscriberListService::class)->optedIn());
        $perPage = 30;
        $page = $this->getPage();

        return [
            'subscribers' => new LengthAwarePaginator(
                $all->forPage($page, $perPage)->values(),
                $all->count(),
                $perPage,
                $page,
                [
                    'path' => request()->url(),
                    'pageName' => 'page',
                ],
            ),
        ];
    }
}; ?>

<div class="space-y-6">
    <div>
        <flux:heading size="xl">Subscribers</flux:heading>
        <flux:text class="mt-1">
            People who ticked Receive UniqBid updates and offers. SMS and WhatsApp columns show how they want sale and account updates. Guests set those after they create an account. Unchecking offers removes them from this list.
        </flux:text>
    </div>

    <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
        <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
            <thead class="bg-zinc-50 text-left dark:bg-zinc-800">
                <tr>
                    <th class="px-4 py-3 font-medium">Name</th>
                    <th class="px-4 py-3 font-medium">Phone</th>
                    <th class="px-4 py-3 font-medium">Email</th>
                    <th class="px-4 py-3 font-medium">SMS</th>
                    <th class="px-4 py-3 font-medium">WhatsApp</th>
                    <th class="px-4 py-3 font-medium">Source</th>
                    <th class="px-4 py-3 font-medium">Opted in</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($subscribers as $index => $subscriber)
                    <tr wire:key="subscriber-{{ $subscriber['source'] }}-{{ $subscriber['phone'] ?? $subscriber['email'] ?? $index }}">
                        <td class="px-4 py-3 font-medium">{{ $subscriber['name'] }}</td>
                        <td class="px-4 py-3 font-mono text-xs sm:text-sm">{{ $subscriber['phone'] ?: '—' }}</td>
                        <td class="px-4 py-3 break-all">{{ $subscriber['email'] ?: '—' }}</td>
                        <td class="px-4 py-3">
                            @if ($subscriber['sms'] === null)
                                <span class="text-zinc-400">—</span>
                            @elseif ($subscriber['sms'])
                                <flux:badge size="sm" color="green">On</flux:badge>
                            @else
                                <flux:badge size="sm" color="zinc">Off</flux:badge>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if ($subscriber['whatsapp'] === null)
                                <span class="text-zinc-400">—</span>
                            @elseif ($subscriber['whatsapp'])
                                <flux:badge size="sm" color="green">On</flux:badge>
                            @else
                                <flux:badge size="sm" color="zinc">Off</flux:badge>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <flux:badge size="sm" :color="$subscriber['source'] === 'Account' ? 'green' : 'zinc'">
                                {{ $subscriber['source'] }}
                            </flux:badge>
                        </td>
                        <td class="px-4 py-3 text-xs text-zinc-600 dark:text-zinc-300">
                            @if ($subscriber['consented_at'])
                                {{ $subscriber['consented_at']->timezone('Africa/Nairobi')->format('d M Y H:i') }} EAT
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-zinc-500">No subscribers yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $subscribers->links() }}</div>
</div>
