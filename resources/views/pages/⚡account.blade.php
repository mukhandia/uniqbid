<?php

use App\Models\AuctionResult;
use App\Models\CustomerVerificationDocument;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentAttempt;
use App\Models\Product;
use App\Models\User;
use App\Services\AuctionBidService;
use App\Services\CustomerAccountService;
use App\Services\DarajaStkService;
use App\Services\OrderService;
use App\Services\RedeemBidService;
use App\Support\Money;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new
#[Layout('layouts.site')]
#[Title('My Account: UniqBid')]
class extends Component {
    use WithFileUploads;

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $verificationFiles = [];

    public string $name = '';

    public string $email = '';

    public string $whatsappNumber = '';

    public ?int $participationFeeLimit = null;

    public ?string $limitPeriod = null;

    public ?int $salePaymentAttemptId = null;

    public ?int $salePaymentResultId = null;

    public string $salePaymentState = 'idle';

    public string $salePaymentMessage = '';

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
        $this->participationFeeLimit = $user->customerControl?->participation_fee_limit;
        $this->limitPeriod = $user->customerControl?->limit_period;
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
            ->with(['orderItems.order'])
            ->latest('id')
            ->limit(20)
            ->get();
    }

    public function payWinningAmount(int $resultId): void
    {
        $user = Auth::user();
        abort_if($user === null || $user->is_admin, 403);

        $result = AuctionResult::query()->with('auctionBid.bidder')->findOrFail($resultId);

        if ($result->auctionBid?->bidder_id !== $user->bidder?->id) {
            abort(403);
        }

        $phone = (string) ($result->auctionBid->bidder->phone ?? $user->phone);
        $this->beginSalePayment($result, $phone);
    }

    private function beginSalePayment(AuctionResult $result, string $phone): void
    {
        $this->salePaymentResultId = $result->id;
        $this->salePaymentMessage = '';

        if ($result->isSalePaid()) {
            $this->salePaymentState = 'success';
            unset($this->bidHistory);

            return;
        }

        try {
            $attempt = app(RedeemBidService::class)->initiateSalePayment($result, $phone);
        } catch (ValidationException $exception) {
            $this->salePaymentState = 'failure';
            $this->salePaymentMessage = (string) (collect($exception->errors())->flatten()->first() ?? 'This payment cannot be started.');

            return;
        }

        $this->salePaymentAttemptId = $attempt->id;
        $daraja = app(DarajaStkService::class);

        if (blank($attempt->checkout_request_id) && $daraja->isReady() && ! app()->runningUnitTests()) {
            try {
                $daraja->requestPayment($attempt);
            } catch (ValidationException $exception) {
                $pending = $exception->errors()['payment_pending'][0] ?? null;

                if (is_string($pending)) {
                    $this->salePaymentState = 'pending';
                    $this->salePaymentMessage = $pending;

                    return;
                }

                $this->salePaymentState = 'failure';
                $this->salePaymentMessage = (string) (collect($exception->errors())->flatten()->first() ?? 'M-Pesa could not start the payment.');

                return;
            }
        }

        $this->salePaymentState = 'pending';
    }

    public function refreshSalePayment(): void
    {
        if ($this->salePaymentState !== 'pending' || $this->salePaymentAttemptId === null) {
            return;
        }

        $attempt = PaymentAttempt::query()->find($this->salePaymentAttemptId);

        if ($attempt === null) {
            return;
        }

        if ($attempt->status === 'initiated' && filled($attempt->checkout_request_id)) {
            app(DarajaStkService::class)->queryPayment($attempt);
            $attempt->refresh();
        }

        if ($attempt->status === 'succeeded') {
            $this->salePaymentState = 'success';
            $this->salePaymentMessage = '';
            unset($this->bidHistory);
            Flux::toast(variant: 'success', text: 'Successful-buy payment received.');

            return;
        }

        if ($attempt->status === 'failed') {
            $this->salePaymentState = 'failure';
            $this->salePaymentMessage = (string) ($attempt->failure_reason ?: 'The payment was not completed.');
        }
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

<div class="mx-auto max-w-4xl px-4 py-8 sm:py-12">
    <div class="rounded-2xl border border-border bg-white p-4 shadow-sm sm:p-8">
        <div class="mx-auto grid h-12 w-12 place-items-center rounded-full bg-navy text-navy-foreground">
            <flux:icon.user class="size-6" />
        </div>
        <h1 class="mt-4 text-center text-2xl font-bold text-navy">My Account</h1>

        @guest
            <p class="mt-2 text-center text-sm text-muted-foreground">
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
                        <h2 class="text-lg font-semibold text-navy">Profile</h2>
                        <form wire:submit="saveProfile" class="mt-4 space-y-4">
                            <flux:input wire:model="name" label="Full name" required />
                            <flux:input wire:model="email" type="email" label="Email (optional)" />
                            <flux:input wire:model="whatsappNumber" label="WhatsApp number (optional)" placeholder="07XX or 01XX XXX XXX" />
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
                    <section data-test="verification-section">
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

                    <section>
                        <div class="flex items-center justify-between gap-3">
                            <h2 class="text-lg font-semibold text-navy">Sell on UniqBid</h2>
                            <flux:button href="{{ route('sell') }}" size="sm" class="bg-accent! text-accent-foreground!" wire:navigate>
                                List a product
                            </flux:button>
                        </div>
                        <p class="mt-2 text-sm text-muted-foreground">
                            Submit a shop listing for admin approval. Each sale, cancellation, and payout shows on the listing.
                        </p>
                        @if ($this->guestListings->isEmpty())
                            <p class="mt-3 text-sm text-muted-foreground">No guest listings yet.</p>
                        @else
                            <div class="mt-4 space-y-3">
                                @foreach ($this->guestListings as $listing)
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
                            <div class="mt-4 space-y-3">
                                @foreach ($this->bidHistory as $entry)
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
                                                'successful' => 'green',
                                                'open', 'pending_payment' => 'amber',
                                                'failed' => 'red',
                                                default => 'zinc',
                                            }">
                                                {{ $entry['label'] }}
                                            </flux:badge>
                                        </div>
                                        @if ($entry['status'] === 'successful')
                                            @php($result = $bid->auction->result)
                                            @php($payoutUnpaid = $result?->fulfilment_status === 'redeemed' && $result->redemption_payout_status !== AuctionResult::REDEMPTION_PAYOUT_PAID)
                                            <div @class([
                                                'border-t px-4 py-4 text-sm',
                                                'border-amber-100 bg-amber-50 text-amber-950' => $payoutUnpaid,
                                                'border-green-100 bg-green-50 text-green-900' => ! $payoutUnpaid,
                                            ])>
                                                <p>Successful buy at {{ Money::kes($bid->amount) }}.</p>
                                                @if ($result?->fulfilment_status === 'redeemed' && $result->redemption_payout_status === AuctionResult::REDEMPTION_PAYOUT_PAID)
                                                    <p class="mt-2">Redeemed for cash. {{ Money::kes((int) $result->redemption_amount) }} was sent to your M-Pesa.</p>
                                                @elseif ($payoutUnpaid && $result->redemption_requested_at !== null)
                                                    <p class="mt-2">Your redeem request has been sent for review.</p>
                                                    @if ($result->sale_payment_status !== AuctionResult::PAYMENT_PAID)
                                                        @if ($salePaymentResultId === $result->id && $salePaymentState === 'pending')
                                                            <div wire:poll.3s.visible="refreshSalePayment" class="mt-3">
                                                                Check your phone for the M-Pesa prompt and enter your PIN. This page keeps checking with Safaricom.
                                                                @if ($salePaymentMessage !== '')
                                                                    <div class="mt-1">{{ $salePaymentMessage }}</div>
                                                                @endif
                                                            </div>
                                                        @else
                                                            @if ($salePaymentResultId === $result->id && $salePaymentState === 'failure' && $salePaymentMessage !== '')
                                                                <p class="mt-3">{{ $salePaymentMessage }}</p>
                                                            @endif
                                                            <p class="mt-2">You can still pay {{ Money::kes($result->sale_price) }} to take the item instead.</p>
                                                            <flux:button class="mt-3 w-full sm:w-auto" wire:click="payWinningAmount({{ $result->id }})" wire:loading.attr="disabled">
                                                                Pay with M-Pesa
                                                            </flux:button>
                                                        @endif
                                                    @endif
                                                @elseif ($payoutUnpaid && $result->sale_payment_status !== AuctionResult::PAYMENT_PAID)
                                                    <p class="mt-2">Cash payout of {{ Money::kes((int) $result->redemption_amount) }} has not been sent. You can try that payout again, or pay {{ Money::kes($result->sale_price) }} to take the item instead.</p>
                                                    @if ($salePaymentResultId === $result->id && $salePaymentState === 'pending')
                                                        <div wire:poll.3s.visible="refreshSalePayment" class="mt-3">
                                                            Check your phone for the M-Pesa prompt and enter your PIN. This page keeps checking with Safaricom.
                                                            @if ($salePaymentMessage !== '')
                                                                <div class="mt-1">{{ $salePaymentMessage }}</div>
                                                            @endif
                                                        </div>
                                                    @else
                                                        @if ($salePaymentResultId === $result->id && $salePaymentState === 'failure' && $salePaymentMessage !== '')
                                                            <p class="mt-3">{{ $salePaymentMessage }}</p>
                                                        @endif
                                                        <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                                                            <flux:button href="{{ route('redeem', ['reference' => $bid->reference]) }}" class="w-full sm:w-auto" wire:navigate>
                                                                Try again
                                                            </flux:button>
                                                            <flux:button class="w-full sm:w-auto" wire:click="payWinningAmount({{ $result->id }})" wire:loading.attr="disabled">
                                                                Pay with M-Pesa
                                                            </flux:button>
                                                        </div>
                                                    @endif
                                                @elseif ($payoutUnpaid)
                                                    <p class="mt-2">Cash payout of {{ Money::kes((int) $result->redemption_amount) }} has not been sent.</p>
                                                    <flux:button href="{{ route('redeem', ['reference' => $bid->reference]) }}" class="mt-3 w-full sm:w-auto" wire:navigate>
                                                        Try again
                                                    </flux:button>
                                                @elseif ($result?->sale_payment_status === 'paid')
                                                    <p class="mt-2">Sale payment received.</p>
                                                @elseif ($result)
                                                    <div class="mt-3">
                                                        <h3 class="font-semibold text-navy">Pay a winning amount</h3>
                                                        <p class="mt-1">
                                                            Pay {{ Money::kes($result->sale_price) }} by {{ $result->fulfilment_due_at?->timezone(config('app.timezone'))->format('j M Y, H:i') ?? 'the agreed deadline' }} to collect or receive this item. The prompt goes to {{ $this->displayPhone($bid->bidder?->phone) }}.
                                                        </p>
                                                        @if ($salePaymentResultId === $result->id && $salePaymentState === 'pending')
                                                            <div wire:poll.3s.visible="refreshSalePayment" class="mt-3">
                                                                Check your phone for the M-Pesa prompt and enter your PIN. This page keeps checking with Safaricom.
                                                                @if ($salePaymentMessage !== '')
                                                                    <div class="mt-1">{{ $salePaymentMessage }}</div>
                                                                @endif
                                                            </div>
                                                        @else
                                                            @if ($salePaymentResultId === $result->id && $salePaymentState === 'failure' && $salePaymentMessage !== '')
                                                                <p class="mt-3">{{ $salePaymentMessage }}</p>
                                                            @endif
                                                            <flux:button class="mt-3 w-full sm:w-auto" wire:click="payWinningAmount({{ $result->id }})" wire:loading.attr="disabled">
                                                                Pay with M-Pesa
                                                            </flux:button>
                                                        @endif
                                                    </div>
                                                @endif
                                                @if ($payoutUnpaid)
                                                    <p class="mt-3 text-xs">Cash payout: not sent.</p>
                                                @elseif ($result?->fulfilment_status === 'redeemed')
                                                    <p class="mt-3 text-xs">Cash payout sent.</p>
                                                @else
                                                    <p class="mt-3 text-xs">Collection / delivery: {{ str_replace('_', ' ', $result?->fulfilment_status ?? 'pending') }}.</p>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
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
