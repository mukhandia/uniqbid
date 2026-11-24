@props([
    'auction',
    'class' => '',
])

<span {{ $attributes->class(['font-mono tabular-nums', $class]) }}>
    {{ $auction->countdownLabel() }}
</span>
