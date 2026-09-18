<?php

use App\Models\Order;
use App\Models\OrderItem;
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
use Livewire\Component;

new
#[Layout('layouts.site')]
#[Title('My Account: UniqBid')]
class extends Component {
    public string $name = '';

    public string $email = '';

    public string $whatsappNumber = '';

    public bool $marketingConsent = false;

    public bool $smsNotifications = true;

    public bool $whatsappNotifications = false;

    public ?int $participationFeeLimit = null;

    public ?string $limitPeriod = null;

    public function mount(): void
    {
        $user = Auth::user();

        if ($user === null || $user->is_admin) {
            return;
        }

        $user->load(['bidder', 'customerControl']);

        $this->name = $user->name;
        $this->email = $user->email ?? '';
        $this->whatsappNumber = $this->displayPhone($user->bidder?->whatsapp_number);
        $this->marketingConsent = (bool) $user->marketing_consent;
        $this->smsNotifications = $this->marketingConsent && (bool) $user->sms_notifications;
        $this->whatsappNotifications = $this->marketingConsent && (bool) $user->whatsapp_notifications;
        $this->participationFeeLimit = $user->customerControl?->participation_fee_limit;
        $this->limitPeriod = $user->customerControl?->limit_period;
    }

    public function updatedMarketingConsent(bool $enabled): void
    {
        if ($enabled) {
            return;
        }

        $this->smsNotifications = false;
        $this->whatsappNotifications = false;
    }

    public function updatedSmsNotifications(): void
    {
        if (! $this->marketingConsent) {
            $this->smsNotifications = false;
        }
    }

    public function updatedWhatsappNotifications(): void
    {
        if (! $this->marketingConsent) {
            $this->whatsappNotifications = false;
        }
    }

    #[Computed]
    public function bidHistory(): Collection
    {
        $user = Auth::user();

        if ($user === null || $user->is_admin) {
            return collect();
        }

        return app(CustomerAccountService::class)->bidHistory($user);
    }

    #[Computed]
    public function shopOrders(): Collection
    {
        $user = Auth::user();

        if ($user === null || $user->is_admin) {
            return collect();
        }

        return $user->orders()->with('items')->limit(20)->get();
    }

    #[Computed]
    public function guestListings(): Collection
    {
        $user = Auth::user();

        if ($user === null || $user->is_admin) {
            return collect();
        }

        return Product::query()
            ->where('seller_user_id', $user->id)
            ->with(['orderItems' => fn ($query) => $query->latest('id')->limit(1)])
            ->latest('id')
            ->limit(20)
            ->get();
    }

    public function cancelShopOrder(int $orderId): void
    {
        $user = Auth::user();
        abort_if($user === null || $user->is_admin, 403);

        $order = Order::query()->whereKey($orderId)->where('user_id', $user->id)->firstOrFail();

        try {
            app(OrderService::class)->cancelBeforeDispatch($order);
            unset($this->shopOrders);
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
            'email' => ['required', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
            'whatsappNumber' => ['nullable', 'string'],
            'marketingConsent' => ['boolean'],
            'smsNotifications' => ['boolean'],
            'whatsappNotifications' => ['boolean'],
        ]);

        $whatsapp = null;

        if (filled($validated['whatsappNumber'])) {
            $whatsapp = app(AuctionBidService::class)->normalizeKenyanPhone($validated['whatsappNumber']);
        }

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'marketing_consent' => $validated['marketingConsent'],
            'marketing_consented_at' => $validated['marketingConsent'] ? now() : null,
            'sms_notifications' => $validated['marketingConsent'] && $validated['smsNotifications'],
            'whatsapp_notifications' => $validated['marketingConsent'] && $validated['whatsappNotifications'],
        ]);

        if ($bidder = $user->bidder) {
            $bidder->update([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'whatsapp_number' => $whatsapp,
                'marketing_consent' => $validated['marketingConsent'],
                'marketing_consented_at' => $validated['marketingConsent'] ? now() : null,
            ]);
        }

        Flux::toast(variant: 'success', text: 'Profile updated.');
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

<div class="mx-auto max-w-4xl px-4 py-12">
    <div class="rounded-2xl border border-border bg-white p-8 shadow-sm">
        <div class="mx-auto grid h-12 w-12 place-items-center rounded-full bg-navy text-navy-foreground">
            <flux:icon.user class="size-6" />
        </div>
        <h1 class="mt-4 text-center text-2xl font-bold text-navy">My Account</h1>

        @guest
            <p class="mt-2 text-center text-sm text-muted-foreground">
                Sign in with your mobile number to view entry history, spending controls, and communication preferences.
            </p>
            <div class="mt-6 flex flex-wrap justify-center gap-2">
                <flux:button href="{{ route('mobile-login') }}" class="bg-accent! text-accent-foreground!">
                    Sign in with mobile
                </flux:button>
                <flux:button href="{{ route('auctions.index') }}" variant="ghost">
                    Browse auctions
                </flux:button>
            </div>
            <div class="mt-8 rounded-xl bg-surface p-4 text-center text-sm text-muted-foreground">
                Signed in as: <span class="font-medium text-zinc-900">Guest</span>
            </div>
        @else
            @if (Auth::user()->is_admin)
                <p class="mt-2 text-center text-sm text-muted-foreground">
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
                <div class="mt-8 rounded-xl bg-surface p-4 text-center text-sm text-muted-foreground">
                    Signed in as: <span class="font-medium text-zinc-900">{{ Auth::user()->email }}</span>
                </div>
            @else
                <p class="mt-2 text-center text-sm text-muted-foreground">
                    Mobile: {{ $this->displayPhone(Auth::user()->phone) }}
                </p>

                @if ($this->isSelfExcluded)
                    <flux:callout variant="warning" class="mt-6" icon="no-symbol">
                        Your account is self-excluded from sales until
                        {{ Auth::user()->customerControl?->self_excluded_until?->timezone(config('app.timezone'))->format('j M Y, H:i') }}.
                    </flux:callout>
                @endif

                <div class="mt-8 space-y-10">
                    <section>
                        <h2 class="text-lg font-semibold text-navy">Profile &amp; preferences</h2>
                        <form wire:submit="saveProfile" class="mt-4 space-y-4">
                            <flux:input wire:model="name" label="Full name" required />
                            <flux:input wire:model="email" type="email" label="Email" required />
                            <flux:input wire:model="whatsappNumber" label="WhatsApp number (optional)" placeholder="07XX or 01XX XXX XXX" />
                            <div class="space-y-2">
                                <flux:checkbox wire:model.live="marketingConsent" label="Receive UniqBid updates and offers" />
                                <flux:checkbox wire:model="smsNotifications" :disabled="! $marketingConsent" label="Send sale and account updates by SMS" />
                                <flux:checkbox wire:model="whatsappNotifications" :disabled="! $marketingConsent" label="Send sale and account updates by WhatsApp" />
                                @unless ($marketingConsent)
                                    <p class="text-xs text-muted-foreground">Tick Receive UniqBid updates and offers first to choose SMS or WhatsApp.</p>
                                @endunless
                            </div>
                            <flux:button type="submit" class="bg-accent! text-accent-foreground!">Save profile</flux:button>
                        </form>
                    </section>

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

                    <section>
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

                    @php($verificationStatus = Auth::user()->customerControl?->verification_status ?? 'not_requested')
                    @if ($verificationStatus !== 'not_requested')
                        <section>
                            <h2 class="text-lg font-semibold text-navy">ID / KRA verification</h2>
                            <p class="mt-1 text-sm text-muted-foreground">
                                Status: <span class="font-medium capitalize text-zinc-900">{{ str_replace('_', ' ', $verificationStatus) }}</span>
                            </p>
                        </section>
                    @endif

                    <section>
                        <div class="flex items-center justify-between gap-3">
                            <h2 class="text-lg font-semibold text-navy">Sell on UniqBid</h2>
                            <flux:button href="{{ route('sell') }}" size="sm" class="bg-accent! text-accent-foreground!" wire:navigate>
                                List a product
                            </flux:button>
                        </div>
                        <p class="mt-2 text-sm text-muted-foreground">
                            Submit a one-unit shop listing for admin approval. Monitor status here; UniqBid staff manage edits and fulfilment.
                        </p>
                        @if ($this->guestListings->isEmpty())
                            <p class="mt-3 text-sm text-muted-foreground">No guest listings yet.</p>
                        @else
                            <div class="mt-4 space-y-3">
                                @foreach ($this->guestListings as $listing)
                                    @php($sale = $listing->orderItems->first())
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
                                                <div class="mt-1 text-xs text-muted-foreground">
                                                    {{ Money::kes($listing->price) }} · 1 unit
                                                    @if ($listing->approval_status === Product::APPROVAL_REJECTED && filled($listing->rejection_reason))
                                                        · {{ $listing->rejection_reason }}
                                                    @endif
                                                </div>
                                                @if ($sale)
                                                    <div class="mt-1 text-xs text-muted-foreground">
                                                        Sold · Payout {{ $sale->seller_payout_status === OrderItem::PAYOUT_PAID ? 'paid' : 'pending' }}
                                                        ({{ Money::kes($sale->seller_payout_amount) }})
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </section>

                    <section>
                        <h2 class="text-lg font-semibold text-navy">Shop orders</h2>
                        @if ($this->shopOrders->isEmpty())
                            <p class="mt-2 text-sm text-muted-foreground">
                                No shop orders yet.
                                <a href="{{ route('shop.index') }}" class="font-medium text-navy underline" wire:navigate>Browse the shop</a>
                                or
                                <a href="{{ route('orders.track') }}" class="font-medium text-navy underline" wire:navigate>track an order</a>.
                            </p>
                        @else
                            <div class="mt-4 space-y-3">
                                @foreach ($this->shopOrders as $order)
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
                        @endif
                    </section>

                    <section>
                        <h2 class="text-lg font-semibold text-navy">Sale history</h2>
                        @if ($this->bidHistory->isEmpty())
                            <p class="mt-2 text-sm text-muted-foreground">No entries yet. Browse live sales to place your first amount.</p>
                        @else
                            <div class="mt-4 overflow-x-auto rounded-xl border border-border">
                                <table class="min-w-full divide-y divide-border text-sm">
                                    <thead class="bg-surface text-left text-muted-foreground">
                                        <tr>
                                            <th class="px-4 py-3 font-medium">Sale</th>
                                            <th class="px-4 py-3 font-medium">Amount</th>
                                            <th class="px-4 py-3 font-medium">Reference</th>
                                            <th class="px-4 py-3 font-medium">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-border bg-white">
                                        @foreach ($this->bidHistory as $entry)
                                            @php($bid = $entry['bid'])
                                            <tr wire:key="bid-{{ $bid->id }}">
                                                <td class="px-4 py-3">
                                                    <a href="{{ route('auctions.show', $bid->auction) }}" class="font-medium text-navy hover:underline" wire:navigate>
                                                        {{ $bid->auction->name }}
                                                    </a>
                                                </td>
                                                <td class="px-4 py-3">{{ Money::kes($bid->amount) }}</td>
                                                <td class="px-4 py-3 font-mono text-xs">{{ $bid->reference }}</td>
                                                <td class="px-4 py-3">
                                                    <flux:badge size="sm" :color="match ($entry['status']) {
                                                        'successful' => 'green',
                                                        'open', 'pending_payment' => 'amber',
                                                        'failed' => 'red',
                                                        default => 'zinc',
                                                    }">
                                                        {{ $entry['label'] }}
                                                    </flux:badge>
                                                </td>
                                            </tr>
                                            @if ($entry['status'] === 'successful')
                                                @php($result = $bid->auction->result)
                                                <tr wire:key="bid-success-{{ $bid->id }}">
                                                    <td colspan="4" class="bg-green-50 px-4 py-3 text-sm text-green-900">
                                                        Successful buy at {{ Money::kes($bid->amount) }}.
                                                        @if ($result?->sale_payment_status === 'paid')
                                                            Sale payment received.
                                                        @else
                                                            Pay the sale price by {{ $result?->fulfilment_due_at?->timezone(config('app.timezone'))->format('j M Y, H:i') ?? 'the agreed deadline' }}.
                                                        @endif
                                                        Collection / delivery: {{ str_replace('_', ' ', $result?->fulfilment_status ?? 'pending') }}.
                                                    </td>
                                                </tr>
                                            @endif
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </section>
                </div>

                <div class="mt-8 flex flex-wrap gap-2">
                    <flux:button href="{{ route('auctions.index') }}" variant="ghost">Browse auctions</flux:button>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <flux:button type="submit" variant="outline">Sign out</flux:button>
                    </form>
                </div>
            @endif
        @endguest
    </div>
</div>
