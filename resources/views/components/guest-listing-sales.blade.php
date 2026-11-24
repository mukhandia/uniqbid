@props(['listing'])

@php($sales = $listing->guestSaleSummary())

<div class="mt-1 text-xs text-muted-foreground">
    {{ \App\Support\Money::kes($listing->price) }}
    @if ($sales['bought'] > 0)
        · Bought {{ $sales['bought'] }} {{ $sales['bought'] === 1 ? 'time' : 'times' }}
    @endif
    @if ($sales['cancelled'] > 0)
        · {{ $sales['cancelled'] }} cancelled
    @endif
    @if ($sales['awaiting'] > 0)
        · {{ $sales['awaiting'] }} awaiting M-Pesa
    @endif
    @if ($listing->approval_status === \App\Models\Product::APPROVAL_REJECTED && filled($listing->rejection_reason))
        · {{ $listing->rejection_reason }}
    @endif
</div>
@foreach ($sales['payouts'] as $payout)
    <div class="mt-1 text-xs text-muted-foreground">
        <span class="font-mono">{{ $payout['reference'] }}</span>
        ·
        @if ($payout['sent'])
            {{ \App\Support\Money::kes($payout['amount']) }} was sent to your M-Pesa.
        @else
            Payout pending ({{ \App\Support\Money::kes($payout['amount']) }})
        @endif
    </div>
@endforeach
