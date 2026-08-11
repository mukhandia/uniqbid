@props([])

{{--
    Horizontal media row that scrolls on every device:
    - 2 / 3 / 5 cards visible (mobile / tablet / desktop)
    - touch swipe, pointer drag, and prev/next controls
--}}
<div
    {{ $attributes->merge(['class' => 'relative']) }}
    x-data="{
        canPrev: false,
        canNext: false,
        dragging: false,
        moved: false,
        startX: 0,
        startScroll: 0,
        update() {
            const el = this.$refs.track;
            if (! el) return;
            const max = el.scrollWidth - el.clientWidth;
            this.canPrev = el.scrollLeft > 2;
            this.canNext = max > 2 && el.scrollLeft < max - 2;
        },
        scrollByPage(dir) {
            const el = this.$refs.track;
            if (! el) return;
            el.scrollBy({ left: dir * el.clientWidth * 0.85, behavior: 'smooth' });
        },
        onPointerDown(e) {
            if (e.pointerType === 'touch') return;
            const el = this.$refs.track;
            this.dragging = true;
            this.moved = false;
            this.startX = e.clientX;
            this.startScroll = el.scrollLeft;
            el.setPointerCapture(e.pointerId);
        },
        onPointerMove(e) {
            if (! this.dragging) return;
            const dx = e.clientX - this.startX;
            if (Math.abs(dx) > 4) this.moved = true;
            this.$refs.track.scrollLeft = this.startScroll - dx;
        },
        onPointerUp(e) {
            if (! this.dragging) return;
            this.dragging = false;
            try { this.$refs.track.releasePointerCapture(e.pointerId); } catch (_) {}
            this.update();
        },
        onClickCapture(e) {
            if (this.moved) {
                e.preventDefault();
                e.stopPropagation();
                this.moved = false;
            }
        },
    }"
    x-init="$nextTick(() => {
        update();
        if ($refs.track) {
            new ResizeObserver(() => update()).observe($refs.track);
        }
    })"
>
    <button
        type="button"
        aria-label="Scroll previous"
        class="absolute -left-1 top-1/2 z-10 grid size-10 -translate-y-1/2 place-items-center rounded-full border border-border bg-white/95 text-navy shadow-sm transition hover:bg-white disabled:pointer-events-none disabled:opacity-0 sm:-left-2 sm:size-11"
        x-bind:disabled="! canPrev"
        x-on:click="scrollByPage(-1)"
    >
        <flux:icon.chevron-left class="size-5" />
    </button>

    <button
        type="button"
        aria-label="Scroll next"
        class="absolute -right-1 top-1/2 z-10 grid size-10 -translate-y-1/2 place-items-center rounded-full border border-border bg-white/95 text-navy shadow-sm transition hover:bg-white disabled:pointer-events-none disabled:opacity-0 sm:-right-2 sm:size-11"
        x-bind:disabled="! canNext"
        x-on:click="scrollByPage(1)"
    >
        <flux:icon.chevron-right class="size-5" />
    </button>

    <div
        x-ref="track"
        class="@container flex cursor-grab gap-4 overflow-x-auto overscroll-x-contain scroll-smooth snap-x snap-mandatory pb-1 touch-pan-x active:cursor-grabbing [scrollbar-width:thin] [-webkit-overflow-scrolling:touch]"
        x-on:scroll.passive="update()"
        x-on:pointerdown="onPointerDown($event)"
        x-on:pointermove="onPointerMove($event)"
        x-on:pointerup="onPointerUp($event)"
        x-on:pointercancel="onPointerUp($event)"
        x-on:click.capture="onClickCapture($event)"
    >
        {{ $slot }}
    </div>
</div>
