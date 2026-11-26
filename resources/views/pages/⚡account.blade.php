<?php

use App\Models\CustomerVerificationDocument;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\AuctionBidService;
use App\Services\CustomerAccountService;
use App\Services\OrderService;
use App\Support\Money;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new
#[Layout('layouts.site')]
#[Title('My Account: UniqBid')]
class extends Component {
    use WithFileUploads;
    use WithPagination;

    /** @var list<string> */
    private const SECTIONS = ['profile', 'settings', 'history', 'orders', 'listings'];

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $verificationFiles = [];

    #[Url(as: 'section', history: true)]
    public string $section = 'profile';

    public string $name = '';

    public string $email = '';

    public string $whatsappNumber = '';

    public ?int $participationFeeLimit = null;

    public ?string $limitPeriod = null;

    public string $historySearch = '';

    public string $ordersSearch = '';

    public string $listingsSearch = '';

    public function mount(): void
    {
        if (! in_array($this->section, self::SECTIONS, true)) {
            $this->section = 'profile';
        }

        $user = Auth::user();

        if ($user === null || $user->is_admin) {
            return;
        }

        $user->load(['bidder', 'customerControl']);

        $this->name = $user->name;
        $this->email = $user->email ?? '';
        $this->whatsappNumber = $this->displayPhone($user->bidder?->whatsapp_number);
        $this->participationFeeLimit = $user->customerControl?->participation_fee_limit;
        $this->limitPeriod = $user->customerControl?->limit_period;
    }

    public function updatedSection(string $value): void
    {
        if (! in_array($value, self::SECTIONS, true)) {
            $this->section = 'profile';

            return;
        }

        $this->resetPage('historyPage');
        $this->resetPage('ordersPage');
        $this->resetPage('listingsPage');
        unset($this->sectionLabel);
    }

    public function updatedHistorySearch(): void
    {
        $this->resetPage('historyPage');
    }

    public function updatedOrdersSearch(): void
    {
        $this->resetPage('ordersPage');
    }

    public function updatedListingsSearch(): void
    {
        $this->resetPage('listingsPage');
    }

    public function showSection(string $section): void
    {
        if (! in_array($section, self::SECTIONS, true)) {
            return;
        }

        $this->section = $section;
    }

    #[Computed]
    public function sectionLabel(): string
    {
        return match ($this->section) {
            'settings' => 'Settings',
            'history' => 'Sales & Auction history',
            'orders' => 'Shop orders',
            'listings' => 'Vendor listings',
            default => 'Profile',
        };
    }

    public function with(): array
    {
        $user = Auth::user();

        if ($user === null || $user->is_admin) {
            return [];
        }

        return match ($this->section) {
            'history' => [
                'bidHistory' => app(CustomerAccountService::class)->bidHistory(
                    $user,
                    perPage: 10,
                    pageName: 'historyPage',
                    search: $this->historySearch,
                ),
            ],
            'orders' => [
                'shopOrders' => $user->orders()
                    ->with('items')
                    ->when(trim($this->ordersSearch) !== '', function ($query): void {
                        $term = '%'.trim($this->ordersSearch).'%';

                        $query->where(function ($builder) use ($term): void {
                            $builder
                                ->where('reference', 'like', $term)
                                ->orWhereHas('items', fn ($items) => $items->where('product_name', 'like', $term));
                        });
                    })
                    ->latest('id')
                    ->paginate(10, pageName: 'ordersPage'),
            ],
            'listings' => [
                'guestListings' => Product::query()
                    ->where('seller_user_id', $user->id)
                    ->when(trim($this->listingsSearch) !== '', function ($query): void {
                        $term = '%'.trim($this->listingsSearch).'%';

                        $query->where(function ($builder) use ($term): void {
                            $builder
                                ->where('name', 'like', $term)
                                ->orWhere('slug', 'like', $term);
                        });
                    })
                    ->with(['orderItems.order'])
                    ->latest('id')
                    ->paginate(10, pageName: 'listingsPage'),
            ],
            default => [],
        };
    }

    public function cancelShopOrder(int $orderId): void
    {
        $user = Auth::user();
        abort_if($user === null || $user->is_admin, 403);

        $order = Order::query()->whereKey($orderId)->where('user_id', $user->id)->firstOrFail();

        try {
            app(OrderService::class)->cancelBeforeDispatch($order);
            Flux::toast(variant: 'success', text: 'Order '.$order->reference.' cancelled.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            Flux::toast(
                variant: 'danger',
                text: collect($exception->errors())->flatten()->first() ?? 'Unable to cancel this order.',
            );
        }
    }

    #[Computed]
    public function isSelfExcluded(): bool
    {
        $user = Auth::user();

        return $user !== null && app(CustomerAccountService::class)->isSelfExcluded($user);
    }

    public function saveProfile(): void
    {
        $user = Auth::user();
        abort_if($user === null || $user->is_admin, 403);

        $validated = $this->validate([
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
            'whatsappNumber' => ['nullable', 'string'],
        ]);

        $whatsapp = null;

        if (filled($validated['whatsappNumber'])) {
            $whatsapp = app(AuctionBidService::class)->normalizeKenyanPhone($validated['whatsappNumber']);
        }

        $email = filled($validated['email']) ? $validated['email'] : null;

        $user->update([
            'name' => $validated['name'],
            'email' => $email,
        ]);

        if ($bidder = $user->bidder) {
            $bidder->update([
                'name' => $validated['name'],
                'email' => $email,
                'whatsapp_number' => $whatsapp,
            ]);
        }

        Flux::toast(variant: 'success', text: 'Profile updated.');
    }

    #[Computed]
    public function verificationDocuments(): Collection
    {
        $user = Auth::user();

        if ($user === null || $user->is_admin) {
            return collect();
        }

        return $user->verificationDocuments()->get();
    }

    public function submitVerification(): void
    {
        $user = Auth::user();
        abort_if($user === null || $user->is_admin, 403);

        $control = app(CustomerAccountService::class)->ensureControl($user);

        if ($control->verification_status === 'verified') {
            Flux::toast(variant: 'success', text: 'Your account is already verified.');

            return;
        }

        $remaining = CustomerVerificationDocument::MAX_FILES - $user->verificationDocuments()->count();

        $this->validate([
            'verificationFiles' => ['required', 'array', 'min:1', 'max:'.max(0, $remaining)],
            'verificationFiles.*' => ['file', 'mimes:'.implode(',', CustomerVerificationDocument::MIMES), 'max:'.CustomerVerificationDocument::MAX_KILOBYTES],
        ], [
            'verificationFiles.required' => 'Choose at least one file to upload.',
            'verificationFiles.max' => $remaining > 0
                ? 'You can upload '.$remaining.' more file(s). Remove an older file to add more.'
                : 'You already have '.CustomerVerificationDocument::MAX_FILES.' files. Remove one to upload another.',
            'verificationFiles.*.mimes' => 'Upload a PDF or an image (JPG, PNG or WEBP).',
            'verificationFiles.*.max' => 'Each file must be 5 MB or smaller.',
        ]);

        foreach ($this->verificationFiles as $file) {
            $user->verificationDocuments()->create([
                'path' => $file->store('verification-documents/'.$user->id, CustomerVerificationDocument::DISK),
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                'mime_type' => (string) $file->getMimeType(),
                'size' => (int) $file->getSize(),
            ]);
        }

        $control->update([
            'verification_status' => 'submitted',
            'verification_submitted_at' => now(),
        ]);
        $user->setRelation('customerControl', $control);

        $this->reset('verificationFiles');
        unset($this->verificationDocuments);

        Flux::toast(variant: 'success', text: 'Documents submitted. We will review them and update your status.');
    }

    public function removeVerificationDocument(int $documentId): void
    {
        $user = Auth::user();
        abort_if($user === null || $user->is_admin, 403);

        $control = $user->customerControl()->first();
        $user->setRelation('customerControl', $control);

        if ($control?->verification_status === 'verified') {
            return;
        }

        $user->verificationDocuments()->whereKey($documentId)->firstOrFail()->delete();
        unset($this->verificationDocuments);

        if ($control?->verification_status === 'submitted' && ! $user->verificationDocuments()->exists()) {
            $control->update([
                'verification_status' => $control->verification_requested_at !== null ? 'requested' : 'not_requested',
                'verification_submitted_at' => null,
            ]);
        }

        Flux::toast(variant: 'success', text: 'File removed.');
    }

    public function saveSpendLimit(): void
    {
        $user = Auth::user();
        abort_if($user === null || $user->is_admin, 403);

        $this->validate([
            'participationFeeLimit' => ['nullable', 'integer', 'min:1'],
            'limitPeriod' => ['nullable', 'required_with:participationFeeLimit', 'in:daily,weekly,monthly'],
        ]);

        $control = app(CustomerAccountService::class)->ensureControl($user);

        if ($this->participationFeeLimit === null) {
            $control->update([
                'participation_fee_limit' => null,
                'limit_period' => null,
            ]);
        } else {
            $control->update([
                'participation_fee_limit' => $this->participationFeeLimit,
                'limit_period' => $this->limitPeriod,
            ]);
        }

        Flux::toast(variant: 'success', text: 'Participation fee limit saved.');
    }

    public function applySelfExclusion(int $days): void
    {
        $user = Auth::user();
        abort_if($user === null || $user->is_admin, 403);
        abort_unless(in_array($days, [30, 60, 90], true), 422);

        $control = app(CustomerAccountService::class)->ensureControl($user);
        $control->update([
            'self_excluded_until' => now()->addDays($days),
        ]);

        unset($this->isSelfExcluded);

        Flux::toast(
            variant: 'success',
            text: 'Self-exclusion active until '.$control->fresh()->self_excluded_until->timezone(config('app.timezone'))->format('j M Y').'.',
        );
    }

    public function displayPhone(?string $phone): string
    {
        if ($phone === null || $phone === '') {
            return '';
        }

        if (str_starts_with($phone, '254')) {
            return '0'.substr($phone, 3);
        }

        return $phone;
    }
}; ?>

@php
    $navItems = [
        'profile' => ['label' => 'Profile', 'icon' => 'user'],
        'settings' => ['label' => 'Settings', 'icon' => 'cog-6-tooth'],
        'history' => ['label' => 'Sales & Auction history', 'icon' => 'clock'],
        'orders' => ['label' => 'Shop orders', 'icon' => 'shopping-bag'],
        'listings' => ['label' => 'Vendor listings', 'icon' => 'clipboard-document-list'],
    ];
@endphp

<div class="mx-auto max-w-6xl px-4 py-6 sm:py-10">
    @guest
        <div class="mx-auto max-w-lg rounded-2xl border border-border bg-white p-6 text-center shadow-sm sm:p-8">
            <div class="mx-auto grid h-12 w-12 place-items-center rounded-full bg-navy text-navy-foreground">
                <flux:icon.user class="size-6" />
            </div>
            <h1 class="mt-4 text-2xl font-bold text-navy">My Account</h1>
            <p class="mt-2 text-sm text-muted-foreground">
                Sign in with your mobile number to view entry history, spending controls, and your profile.
            </p>
            <div class="mt-6 flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:justify-center">
                <flux:button href="{{ route('mobile-login') }}" class="w-full bg-accent! text-accent-foreground! sm:w-auto">
                    Sign in with mobile
                </flux:button>
                <flux:button href="{{ route('auctions.index') }}" variant="ghost" class="w-full sm:w-auto">
                    Browse auctions
                </flux:button>
            </div>
            <div class="mt-8 rounded-xl bg-surface p-4 text-sm text-muted-foreground">
                Signed in as: <span class="font-medium text-zinc-900">Guest</span>
            </div>
        </div>
    @else
        @if (Auth::user()->is_admin)
            <div class="mx-auto max-w-lg rounded-2xl border border-border bg-white p-6 text-center shadow-sm sm:p-8">
                <div class="mx-auto grid h-12 w-12 place-items-center rounded-full bg-navy text-navy-foreground">
                    <flux:icon.user class="size-6" />
                </div>
                <h1 class="mt-4 text-2xl font-bold text-navy">My Account</h1>
                <p class="mt-2 text-sm text-muted-foreground">
                    Admin account: use the dashboard for sale management.
                </p>
                <div class="mt-6 flex flex-wrap justify-center gap-2">
                    <flux:button href="{{ route('dashboard') }}" class="bg-accent! text-accent-foreground!">
                        Go to dashboard
                    </flux:button>
                    <flux:button href="{{ route('profile.edit') }}" variant="outline">
                        Profile settings
                    </flux:button>
                    <flux:button href="{{ route('auctions.index') }}" variant="ghost">
                        Browse auctions
                    </flux:button>
                </div>
                <div class="mt-8 rounded-xl bg-surface p-4 text-sm text-muted-foreground">
                    Signed in as: <span class="font-medium text-zinc-900">{{ Auth::user()->email }}</span>
                </div>
            </div>
        @else
            <div class="overflow-hidden rounded-2xl border border-border bg-white shadow-sm lg:grid lg:grid-cols-[15.5rem_minmax(0,1fr)]">
                {{-- Desktop sidebar --}}
                <aside class="hidden border-e border-border bg-surface lg:flex lg:flex-col">
                    <div class="border-b border-border px-4 py-4">
                        <div class="text-xs font-semibold uppercase tracking-[0.16em] text-gold">My Account</div>
                        <div class="mt-1 truncate text-sm font-semibold text-navy">{{ Auth::user()->name }}</div>
                        <div class="truncate text-xs text-muted-foreground">{{ $this->displayPhone(Auth::user()->phone) }}</div>
                    </div>

                    <nav class="flex flex-1 flex-col gap-1 p-3" aria-label="Account sections">
                        @foreach ($navItems as $key => $item)
                            <button
                                type="button"
                                wire:click="showSection('{{ $key }}')"
                                @class([
                                    'flex w-full items-center gap-2.5 rounded-lg px-3 py-2.5 text-left text-sm font-medium transition',
                                    'bg-navy text-navy-foreground' => $section === $key,
                                    'text-zinc-700 hover:bg-white' => $section !== $key,
                                ])
                            >
                                <flux:icon :name="$item['icon']" class="size-4 shrink-0" />
                                <span class="min-w-0 leading-snug">{{ $item['label'] }}</span>
                            </button>
                        @endforeach
                    </nav>

                    <div class="mt-auto space-y-2 border-t border-border p-3">
                        <flux:button href="{{ route('auctions.index') }}" variant="ghost" class="w-full justify-start" wire:navigate>
                            Browse auctions
                        </flux:button>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <flux:button type="submit" variant="outline" class="w-full justify-start" icon="arrow-right-start-on-rectangle">
                                Sign out
                            </flux:button>
                        </form>
                    </div>
                </aside>

                {{-- Main panel --}}
                <div class="min-w-0">
                    {{-- Mobile top bar --}}
                    <div class="flex items-center gap-3 border-b border-border px-4 py-3 lg:hidden" x-data="{ accountMenuOpen: false }" @keydown.escape.window="accountMenuOpen = false" x-on:account-section-changed.window="accountMenuOpen = false">
                        <button type="button" @click="accountMenuOpen = true" aria-label="Open account menu" aria-controls="mobile-account-drawer" :aria-expanded="accountMenuOpen" class="inline-flex size-11 shrink-0 items-center justify-center rounded-lg border border-navy/15 bg-navy/5 text-navy shadow-sm transition hover:bg-navy/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-navy">
                            <flux:icon name="bars-2" class="size-5" />
                        </button>

                        <template x-teleport="body">
                            <div x-cloak x-show="accountMenuOpen" class="fixed inset-0 z-[100] lg:hidden" aria-label="Account navigation">
                                <button type="button" @click="accountMenuOpen = false" class="absolute inset-0 bg-zinc-950/50" aria-label="Close account menu"></button>
                                <aside
                                    id="mobile-account-drawer"
                                    x-show="accountMenuOpen"
                                    x-transition:enter="transform transition ease-out duration-300"
                                    x-transition:enter-start="-translate-x-full"
                                    x-transition:enter-end="translate-x-0"
                                    x-transition:leave="transform transition ease-in duration-200"
                                    x-transition:leave-start="translate-x-0"
                                    x-transition:leave-end="-translate-x-full"
                                    class="absolute inset-y-0 start-0 flex w-[min(21rem,88vw)] flex-col border-e border-border bg-white shadow-2xl"
                                    role="dialog"
                                    aria-modal="true"
                                    aria-labelledby="mobile-account-drawer-title"
                                >
                                    <div class="flex items-start justify-between border-b border-border bg-surface px-5 py-5">
                                        <div class="min-w-0">
                                            <div id="mobile-account-drawer-title" class="text-xs font-semibold uppercase tracking-[0.16em] text-gold">My Account</div>
                                            <div class="mt-1 truncate text-base font-semibold text-navy">{{ Auth::user()->name }}</div>
                                            <div class="mt-0.5 truncate text-sm text-muted-foreground">{{ $this->displayPhone(Auth::user()->phone) }}</div>
                                        </div>
                                        <button type="button" @click="accountMenuOpen = false" aria-label="Close account menu" class="ms-3 inline-flex size-10 shrink-0 items-center justify-center rounded-lg text-zinc-600 transition hover:bg-white hover:text-navy focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-navy">
                                            <flux:icon name="x-mark" class="size-5" />
                                        </button>
                                    </div>

                                    <nav class="flex-1 space-y-1 overflow-y-auto p-4" aria-label="Account sections">
                                        @foreach ($navItems as $key => $item)
                                            <button
                                                type="button"
                                                wire:click="showSection('{{ $key }}')"
                                                @click="accountMenuOpen = false"
                                                @class([
                                                    'flex min-h-12 w-full items-center gap-3 rounded-xl px-4 py-3 text-start text-base font-medium transition',
                                                    'bg-navy text-navy-foreground shadow-sm' => $section === $key,
                                                    'text-zinc-700 hover:bg-surface' => $section !== $key,
                                                ])
                                            >
                                                <flux:icon :name="$item['icon']" class="size-5 shrink-0" />
                                                <span class="min-w-0 leading-snug">{{ $item['label'] }}</span>
                                            </button>
                                        @endforeach
                                    </nav>

                                    <div class="space-y-2 border-t border-border p-4">
                                        <flux:button href="{{ route('auctions.index') }}" variant="ghost" class="min-h-12 w-full justify-start px-4" icon="scale" wire:navigate>
                                            Browse auctions
                                        </flux:button>
                                        <form method="POST" action="{{ route('logout') }}">
                                            @csrf
                                            <flux:button type="submit" variant="outline" class="min-h-12 w-full justify-start px-4" icon="arrow-right-start-on-rectangle">
                                                Sign out
                                            </flux:button>
                                        </form>
                                    </div>
                                </aside>
                            </div>
                        </template>
                        <div class="min-w-0 flex-1">
                            <div class="text-xs font-semibold uppercase tracking-[0.16em] text-gold">My Account</div>
                            <div class="truncate text-sm font-semibold text-navy">{{ $this->sectionLabel }}</div>
                        </div>
                    </div>

                    <div class="p-4 sm:p-6 lg:p-8">
                        <div class="hidden lg:block">
                            <h1 class="text-2xl font-bold text-navy">{{ $this->sectionLabel }}</h1>
                            <p class="mt-1 text-sm text-muted-foreground">
                                Mobile: {{ $this->displayPhone(Auth::user()->phone) }}
                            </p>
                        </div>

                        @if ($this->isSelfExcluded)
                            <flux:callout variant="warning" class="mt-4 lg:mt-6" icon="no-symbol">
                                Your account is self-excluded from sales until
                                {{ Auth::user()->customerControl?->self_excluded_until?->timezone(config('app.timezone'))->format('j M Y, H:i') }}.
                            </flux:callout>
                        @endif

                        <div class="mt-5 space-y-8 lg:mt-6" wire:key="account-section-{{ $section }}">
                            @if ($section === 'profile')
                                <section>
                                    <h2 class="text-lg font-semibold text-navy lg:sr-only">Profile</h2>
                                    <form wire:submit="saveProfile" class="mt-4 space-y-4 lg:mt-0">
                                        <flux:input wire:model="name" label="Full name" required />
                                        <flux:input wire:model="email" type="email" label="Email (optional)" />
                                        <flux:input wire:model="whatsappNumber" label="WhatsApp number (optional)" placeholder="07XX or 01XX XXX XXX" />
                                        <flux:button type="submit" class="bg-accent! text-accent-foreground!">Save profile</flux:button>
                                    </form>
                                </section>

                                @php($verificationStatus = Auth::user()->customerControl?->verification_status ?? 'not_requested')
                                <section data-test="verification-section" class="border-t border-border pt-8">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <h2 class="text-lg font-semibold text-navy">ID / KRA verification</h2>
                                        <flux:badge size="sm" :color="match ($verificationStatus) {
                                            'verified' => 'green',
                                            'submitted', 'requested' => 'amber',
                                            'rejected' => 'red',
                                            default => 'zinc',
                                        }">
                                            {{ match ($verificationStatus) {
                                                'not_requested' => 'Not submitted',
                                                'requested' => 'Requested by UniqBid',
                                                'submitted' => 'Submitted, awaiting review',
                                                'verified' => 'Verified',
                                                'rejected' => 'Rejected, please upload again',
                                                default => ucfirst(str_replace('_', ' ', $verificationStatus)),
                                            } }}
                                        </flux:badge>
                                    </div>
                                    <p class="mt-1 text-sm text-muted-foreground">
                                        Upload a clear copy of your National ID or passport and your KRA PIN certificate. PDF, JPG, PNG or WEBP, up to 5 MB each, {{ CustomerVerificationDocument::MAX_FILES }} files at most. Only UniqBid staff can see them.
                                    </p>

                                    @if ($this->verificationDocuments->isNotEmpty())
                                        <ul class="mt-4 divide-y divide-border rounded-xl border border-border">
                                            @foreach ($this->verificationDocuments as $document)
                                                <li wire:key="verification-document-{{ $document->id }}" class="flex items-center gap-3 px-4 py-3 text-sm">
                                                    @if ($document->isPdf())
                                                        <flux:icon.document-text class="size-5 shrink-0 text-navy" />
                                                    @else
                                                        <flux:icon.photo class="size-5 shrink-0 text-navy" />
                                                    @endif
                                                    <div class="min-w-0 flex-1">
                                                        <div class="truncate font-medium text-zinc-900">{{ $document->original_name }}</div>
                                                        <div class="text-xs text-muted-foreground">{{ $document->humanSize() }} · uploaded {{ $document->created_at?->timezone(config('app.timezone'))->format('j M Y') }}</div>
                                                    </div>
                                                    @if ($verificationStatus !== 'verified')
                                                        <flux:button size="sm" variant="ghost" wire:click="removeVerificationDocument({{ $document->id }})" wire:confirm="Remove {{ $document->original_name }}?">
                                                            Remove
                                                        </flux:button>
                                                    @endif
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif

                                    @if ($verificationStatus !== 'verified')
                                        <form wire:submit="submitVerification" class="mt-4 space-y-3">
                                            <flux:field>
                                                <flux:label>Documents</flux:label>
                                                <input
                                                    type="file"
                                                    wire:model="verificationFiles"
                                                    multiple
                                                    accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/*"
                                                    class="block w-full text-sm text-zinc-700 file:mr-3 file:rounded-md file:border-0 file:bg-surface file:px-3 file:py-2 file:text-sm file:font-medium"
                                                    data-test="verification-files"
                                                />
                                                <div wire:loading wire:target="verificationFiles" class="text-xs text-muted-foreground">Uploading…</div>
                                                <flux:error name="verificationFiles" />
                                                <flux:error name="verificationFiles.*" />
                                            </flux:field>
                                            <flux:button type="submit" class="bg-accent! text-accent-foreground!" wire:loading.attr="disabled" wire:target="verificationFiles,submitVerification">
                                                Submit for verification
                                            </flux:button>
                                        </form>
                                    @endif
                                </section>
                            @endif

                            @if ($section === 'settings')
                                <section>
                                    <h2 class="text-lg font-semibold text-navy">Participation fee limit</h2>
                                    <p class="mt-1 text-sm text-muted-foreground">
                                        Optional. Leave blank for no limit; you choose whether to set one.
                                    </p>
                                    <form wire:submit="saveSpendLimit" class="mt-4 grid gap-4 sm:grid-cols-2">
                                        <flux:input wire:model="participationFeeLimit" type="number" min="1" label="Limit (KES)" placeholder="No limit" />
                                        <flux:select wire:model="limitPeriod" label="Period">
                                            <flux:select.option value="">No limit</flux:select.option>
                                            <flux:select.option value="daily">Daily</flux:select.option>
                                            <flux:select.option value="weekly">Weekly</flux:select.option>
                                            <flux:select.option value="monthly">Monthly</flux:select.option>
                                        </flux:select>
                                        <div class="sm:col-span-2">
                                            <flux:button type="submit" variant="outline">Save limit</flux:button>
                                        </div>
                                    </form>
                                </section>

                                <section class="border-t border-border pt-8">
                                    <h2 class="text-lg font-semibold text-navy">Self-exclusion</h2>
                                    <p class="mt-1 text-sm text-muted-foreground">
                                        Pause participation for at least 30 days. This cannot be reversed early.
                                    </p>
                                    <div class="mt-4 flex flex-wrap gap-2">
                                        <flux:button wire:click="applySelfExclusion(30)" variant="outline" wire:confirm="Self-exclude from sales for 30 days?">
                                            30 days
                                        </flux:button>
                                        <flux:button wire:click="applySelfExclusion(60)" variant="outline" wire:confirm="Self-exclude from sales for 60 days?">
                                            60 days
                                        </flux:button>
                                        <flux:button wire:click="applySelfExclusion(90)" variant="outline" wire:confirm="Self-exclude from sales for 90 days?">
                                            90 days
                                        </flux:button>
                                    </div>
                                </section>
                            @endif

                            @if ($section === 'history')
                                <section wire:loading.class="opacity-60" wire:target="gotoPage,nextPage,previousPage,showSection,historySearch">
                                    <h2 class="text-lg font-semibold text-navy lg:sr-only">Sales & Auction history</h2>
                                    <div class="mt-4 lg:mt-0">
                                        <flux:input
                                            wire:model.live.debounce.400ms="historySearch"
                                            placeholder="Filter by product name or bid number"
                                            icon="magnifying-glass"
                                        />
                                    </div>
                                    @if ($bidHistory->isEmpty())
                                        <p class="mt-3 text-sm text-muted-foreground">
                                            @if (trim($historySearch) !== '')
                                                No matches for “{{ $historySearch }}”.
                                            @else
                                                No entries yet. Browse live sales to place your first amount.
                                            @endif
                                        </p>
                                    @else
                                        <div class="mt-4 space-y-3">
                                            @foreach ($bidHistory as $entry)
                                                @php($bid = $entry['bid'])
                                                <div class="overflow-hidden rounded-xl border border-border" wire:key="bid-{{ $bid->id }}">
                                                    <div class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
                                                        <div class="min-w-0">
                                                            <a href="{{ route('auctions.show', $bid->auction) }}" class="block truncate font-medium text-navy hover:underline" wire:navigate>
                                                                {{ $bid->auction->name }}
                                                            </a>
                                                            <div class="mt-1 text-sm text-muted-foreground">
                                                                {{ Money::kes($bid->amount) }}
                                                                <span class="mx-1">·</span>
                                                                <span class="font-mono text-xs">{{ $bid->reference }}</span>
                                                            </div>
                                                        </div>
                                                        <flux:badge size="sm" class="w-fit" :color="match ($entry['status']) {
                                                            'successful', 'unsuccessful', 'refund_successful' => 'green',
                                                            'open', 'pending_payment', 'awaiting_refund' => 'amber',
                                                            'failed', 'refund_failed' => 'red',
                                                            default => 'zinc',
                                                        }">
                                                            {{ $entry['label'] }}
                                                        </flux:badge>
                                                    </div>
                                                    @php($refund = app(CustomerAccountService::class)->refundState($bid))
                                                    @if ($refund === 'pending')
                                                        <div wire:poll.5s.visible class="border-t border-border px-4 py-3 text-sm text-muted-foreground">
                                                            Refund is with M-Pesa.
                                                        </div>
                                                    @elseif ($refund === 'successful')
                                                        <div class="border-t border-border px-4 py-3 text-sm text-muted-foreground">
                                                            Refund successful.
                                                        </div>
                                                    @elseif ($refund === 'failed')
                                                        <div class="border-t border-border px-4 py-3 text-sm text-muted-foreground">
                                                            Refund failed.
                                                        </div>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                        <div class="mt-4">{{ $bidHistory->links() }}</div>
                                    @endif
                                </section>
                            @endif

                            @if ($section === 'orders')
                                <section wire:loading.class="opacity-60" wire:target="gotoPage,nextPage,previousPage,showSection,ordersSearch">
                                    <h2 class="text-lg font-semibold text-navy lg:sr-only">Shop orders</h2>
                                    <div class="mt-4 lg:mt-0">
                                        <flux:input
                                            wire:model.live.debounce.400ms="ordersSearch"
                                            placeholder="Filter by product name or order number"
                                            icon="magnifying-glass"
                                        />
                                    </div>
                                    @if ($shopOrders->isEmpty())
                                        <p class="mt-3 text-sm text-muted-foreground">
                                            @if (trim($ordersSearch) !== '')
                                                No matches for “{{ $ordersSearch }}”.
                                            @else
                                                No shop orders yet.
                                                <a href="{{ route('shop.index') }}" class="font-medium text-navy underline" wire:navigate>Browse the shop</a>
                                                or
                                                <a href="{{ route('orders.track') }}" class="font-medium text-navy underline" wire:navigate>track an order</a>.
                                            @endif
                                        </p>
                                    @else
                                        <div class="mt-4 space-y-3">
                                            @foreach ($shopOrders as $order)
                                                <div class="rounded-xl border border-border p-4" wire:key="order-{{ $order->id }}">
                                                    <div class="flex flex-wrap items-start justify-between gap-2">
                                                        <div>
                                                            <a href="{{ route('orders.show', $order) }}" class="font-mono text-sm font-semibold text-navy hover:underline" wire:navigate>
                                                                {{ $order->reference }}
                                                            </a>
                                                            <div class="mt-1 text-xs text-muted-foreground">
                                                                {{ $order->created_at?->timezone(config('app.timezone'))->format('j M Y') }}
                                                                · {{ $order->items->sum('quantity') }} item(s)
                                                                · {{ Money::kes($order->total) }}
                                                            </div>
                                                        </div>
                                                        <flux:badge size="sm" :color="match ($order->status) {
                                                            'paid', 'processing' => 'amber',
                                                            'dispatched' => 'sky',
                                                            'delivered' => 'green',
                                                            'cancelled' => 'red',
                                                            default => 'zinc',
                                                        }">
                                                            {{ $order->statusLabel() }}
                                                        </flux:badge>
                                                    </div>
                                                    @if ($order->canCancelBeforeDispatch())
                                                        <flux:button
                                                            size="sm"
                                                            variant="ghost"
                                                            class="mt-2"
                                                            wire:click="cancelShopOrder({{ $order->id }})"
                                                            wire:confirm="Cancel order {{ $order->reference }} before dispatch?"
                                                        >
                                                            Cancel before dispatch
                                                        </flux:button>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                        <div class="mt-4">{{ $shopOrders->links() }}</div>
                                    @endif
                                </section>
                            @endif

                            @if ($section === 'listings')
                                <section wire:loading.class="opacity-60" wire:target="gotoPage,nextPage,previousPage,showSection,listingsSearch">
                                    <div class="flex items-center justify-between gap-3">
                                        <h2 class="text-lg font-semibold text-navy lg:sr-only">Vendor listings</h2>
                                        <flux:button href="{{ route('sell') }}" size="sm" class="ms-auto bg-accent! text-accent-foreground! lg:ms-0" wire:navigate>
                                            List a product
                                        </flux:button>
                                    </div>
                                    <p class="mt-2 text-sm text-muted-foreground">
                                        Submit a shop listing for admin approval. Each sale, cancellation, and payout shows on the listing.
                                    </p>
                                    <div class="mt-4">
                                        <flux:input
                                            wire:model.live.debounce.400ms="listingsSearch"
                                            placeholder="Filter by product name"
                                            icon="magnifying-glass"
                                        />
                                    </div>
                                    @if ($guestListings->isEmpty())
                                        <p class="mt-3 text-sm text-muted-foreground">
                                            @if (trim($listingsSearch) !== '')
                                                No matches for “{{ $listingsSearch }}”.
                                            @else
                                                No vendor listings yet.
                                            @endif
                                        </p>
                                    @else
                                        <div class="mt-4 space-y-3">
                                            @foreach ($guestListings as $listing)
                                                <div class="rounded-xl border border-border p-4" wire:key="guest-listing-{{ $listing->id }}">
                                                    <div class="flex items-start gap-3">
                                                        <img src="{{ $listing->image }}" alt="" class="size-14 rounded-lg object-cover">
                                                        <div class="min-w-0 flex-1">
                                                            <div class="flex flex-wrap items-center gap-2">
                                                                <div class="font-semibold text-navy">{{ $listing->name }}</div>
                                                                <flux:badge size="sm" :color="$listing->guestListingStatusColor()">
                                                                    {{ $listing->guestListingStatusLabel() }}
                                                                </flux:badge>
                                                            </div>
                                                            <x-guest-listing-sales :listing="$listing" />
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                        <div class="mt-4">{{ $guestListings->links() }}</div>
                                    @endif
                                </section>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endif
    @endguest
</div>
