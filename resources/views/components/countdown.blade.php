@props([
    'target',
    'class' => '',
])

<span
    {{ $attributes->class($class) }}
    x-data="{
        target: {{ (int) $target }},
        now: Date.now(),
        get label() {
            const diff = Math.max(0, this.target - this.now);
            if (diff === 0) return 'Closed';
            const d = Math.floor(diff / 86400000);
            const h = Math.floor((diff % 86400000) / 3600000);
            const m = Math.floor((diff % 3600000) / 60000);
            return `${d}d : ${String(h).padStart(2, '0')}h : ${String(m).padStart(2, '0')}m`;
        }
    }"
    x-init="setInterval(() => now = Date.now(), 1000)"
    x-text="label"
></span>
