@props([])

{{--
    Card width is based on the scroll track's container query width (cqw),
    so ~2 / 3 / 5 items fit the viewport on mobile / tablet / desktop.
    Slightly under-full so the next card peeks and scroll is discoverable.
--}}
<div
    data-scroll-row-original
    {{ $attributes->merge(['class' => 'w-[calc((100cqw-1rem)/2.12)] shrink-0 snap-start md:w-[calc((100cqw-2rem)/3.15)] lg:w-[calc((100cqw-4rem)/5.2)]']) }}
>
    {{ $slot }}
</div>
