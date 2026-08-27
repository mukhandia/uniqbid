@props([
    'embedUrl' => null,
    'title' => 'Office location map',
])

<div {{ $attributes->class('aspect-[4/3] min-h-56 w-full overflow-hidden rounded-lg border border-border bg-muted') }}>
    @if ($embedUrl)
        <iframe
            src="{{ $embedUrl }}"
            class="h-full min-h-56 w-full border-0"
            loading="lazy"
            referrerpolicy="no-referrer-when-downgrade"
            allowfullscreen
            title="{{ $title }}"
        ></iframe>
    @else
        <div class="grid h-full min-h-56 place-items-center px-4 text-center text-xs text-muted-foreground">
            Add a map share URL in Settings to show the office location.
        </div>
    @endif
</div>
