@props([
    'loop' => false,
    'autoplay' => false,
    'interval' => 4000,
])

@php
    $loop = filter_var($loop, FILTER_VALIDATE_BOOLEAN);
    $autoplay = filter_var($autoplay, FILTER_VALIDATE_BOOLEAN);
    $interval = max(2500, (int) $interval);
@endphp

{{--
    Horizontal card slider:
    - 2 / 3 / 5 cards visible (mobile / tablet / desktop)
    - arrows only (no scrollbar)
    - optional infinite loop + autoplay
--}}
<div
    @if ($loop) wire:ignore @endif
    {{ $attributes->merge(['class' => 'relative']) }}
    x-data="{
        loop: {{ $loop ? 'true' : 'false' }},
        autoplay: {{ $autoplay ? 'true' : 'false' }},
        intervalMs: {{ $interval }},
        index: 0,
        originalCount: 0,
        stride: 0,
        offset: 0,
        motion: false,
        dragging: false,
        moved: false,
        lock: null,
        startX: 0,
        startY: 0,
        startOffset: 0,
        timer: null,
        canPrev: false,
        canNext: false,
        itemStride() {
            const track = this.$refs.track;
            const item = track?.children?.[0];
            if (! item) return 0;
            const gap = parseFloat(getComputedStyle(track).gap || '16') || 16;
            return item.getBoundingClientRect().width + gap;
        },
        visibleCount() {
            const width = this.$refs.viewport?.clientWidth || 0;
            if (this.stride <= 0) return 1;
            return Math.max(1, Math.floor((width + 8) / this.stride));
        },
        maxIndex() {
            return Math.max(0, this.originalCount - this.visibleCount());
        },
        applyOffset() {
            this.offset = -this.index * this.stride;
        },
        updateArrows() {
            if (this.loop && this.originalCount > 0) {
                this.canPrev = true;
                this.canNext = true;
                return;
            }
            this.canPrev = this.index > 0;
            this.canNext = this.index < this.maxIndex();
        },
        fillLoop() {
            if (! this.loop || this.originalCount === 0) return;
            const track = this.$refs.track;
            const templates = Array.from(track.querySelectorAll('[data-scroll-row-original]'));
            const visible = this.visibleCount() + 1;
            while (track.children.length < this.originalCount * 3 + visible) {
                templates.forEach((node) => {
                    const clone = node.cloneNode(true);
                    clone.removeAttribute('data-scroll-row-original');
                    clone.setAttribute('data-scroll-row-clone', '');
                    track.appendChild(clone);
                });
            }
            templates.slice().reverse().forEach((node) => {
                const clone = node.cloneNode(true);
                clone.removeAttribute('data-scroll-row-original');
                clone.setAttribute('data-scroll-row-clone', '');
                track.insertBefore(clone, track.firstChild);
            });
            this.index = this.originalCount;
            this.applyOffset();
        },
        resetTrack() {
            const track = this.$refs.track;
            if (! track) return;
            track.querySelectorAll('[data-scroll-row-clone]').forEach((node) => node.remove());
            this.index = 0;
            this.offset = 0;
            this.motion = false;
        },
        setup() {
            const track = this.$refs.track;
            if (! track) return;
            this.stopAutoplay();
            this.resetTrack();
            this.originalCount = track.querySelectorAll('[data-scroll-row-original]').length;
            if (this.originalCount === 0) {
                this.originalCount = track.children.length;
            }
            this.stride = this.itemStride();
            this.fillLoop();
            this.stride = this.itemStride();
            this.applyOffset();
            this.updateArrows();
            this.$nextTick(() => { this.motion = true; });
            this.startAutoplay();
            if (this.$refs.viewport) {
                new ResizeObserver(() => this.onResize()).observe(this.$refs.viewport);
            }
        },
        onResize() {
            const previous = this.stride;
            this.stride = this.itemStride();
            if (previous === this.stride) {
                this.updateArrows();
                return;
            }
            this.motion = false;
            this.applyOffset();
            this.updateArrows();
            this.$nextTick(() => { this.motion = true; });
        },
        go(dir) {
            if (this.originalCount === 0) return;
            this.stride = this.itemStride() || this.stride;
            if (this.loop) {
                this.index += dir;
            } else {
                this.index = Math.min(this.maxIndex(), Math.max(0, this.index + dir));
            }
            this.motion = true;
            this.applyOffset();
            this.updateArrows();
        },
        normalize(e) {
            if (e && e.target !== this.$refs.track) return;
            if (e && e.propertyName && e.propertyName !== 'transform') return;
            if (! this.loop || this.originalCount === 0) return;
            if (this.index >= this.originalCount * 2) {
                this.jumpTo(this.index - this.originalCount);
            } else if (this.index < this.originalCount) {
                this.jumpTo(this.index + this.originalCount);
            }
        },
        jumpTo(nextIndex) {
            this.motion = false;
            this.index = nextIndex;
            this.applyOffset();
            requestAnimationFrame(() => {
                requestAnimationFrame(() => { this.motion = true; });
            });
        },
        startAutoplay() {
            this.stopAutoplay();
            if (! this.autoplay || this.originalCount === 0) return;
            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
            this.timer = setInterval(() => this.go(1), this.intervalMs);
        },
        stopAutoplay() {
            if (this.timer) {
                clearInterval(this.timer);
                this.timer = null;
            }
        },
        onPointerDown(e) {
            this.dragging = true;
            this.moved = false;
            this.lock = null;
            this.startX = e.clientX;
            this.startY = e.clientY;
            this.startOffset = this.offset;
        },
        onPointerMove(e) {
            if (! this.dragging) return;
            const dx = e.clientX - this.startX;
            const dy = e.clientY - this.startY;
            if (! this.lock) {
                if (Math.abs(dx) < 6 && Math.abs(dy) < 6) return;
                this.lock = Math.abs(dx) > Math.abs(dy) ? 'x' : 'y';
                if (this.lock === 'y') return;
                this.motion = false;
                this.stopAutoplay();
            }
            if (this.lock !== 'x') return;
            if (! this.$refs.viewport.hasPointerCapture(e.pointerId)) {
                this.$refs.viewport.setPointerCapture(e.pointerId);
            }
            e.preventDefault();
            this.moved = true;
            this.offset = this.startOffset + dx;
        },
        onPointerUp(e) {
            if (! this.dragging) return;
            this.dragging = false;
            try { this.$refs.viewport.releasePointerCapture(e.pointerId); } catch (_) {}
            if (this.lock !== 'x') {
                this.lock = null;
                return;
            }
            this.lock = null;
            this.stride = this.itemStride() || this.stride;
            if (this.stride > 0) {
                const raw = Math.round(-this.offset / this.stride);
                if (this.loop) {
                    this.index = raw;
                } else {
                    this.index = Math.min(this.maxIndex(), Math.max(0, raw));
                }
            }
            this.motion = true;
            this.applyOffset();
            this.updateArrows();
            this.startAutoplay();
        },
        onClickCapture(e) {
            if (this.moved) {
                e.preventDefault();
                e.stopPropagation();
                this.moved = false;
            }
        },
    }"
    x-init="(() => { $nextTick(() => setup()); return () => stopAutoplay(); })()"
    x-on:mouseenter="stopAutoplay()"
    x-on:mouseleave="startAutoplay()"
>
    <button
        type="button"
        aria-label="Previous"
        class="absolute -left-1 top-1/2 z-10 grid size-10 -translate-y-1/2 place-items-center rounded-full border border-border bg-white/95 text-navy shadow-sm transition hover:bg-white disabled:cursor-not-allowed disabled:opacity-40 sm:-left-2 sm:size-11"
        x-bind:disabled="! canPrev"
        x-on:click="go(-1)"
    >
        <flux:icon.chevron-left class="size-5" />
    </button>

    <button
        type="button"
        aria-label="Next"
        class="absolute -right-1 top-1/2 z-10 grid size-10 -translate-y-1/2 place-items-center rounded-full border border-border bg-white/95 text-navy shadow-sm transition hover:bg-white disabled:cursor-not-allowed disabled:opacity-40 sm:-right-2 sm:size-11"
        x-bind:disabled="! canNext"
        x-on:click="go(1)"
    >
        <flux:icon.chevron-right class="size-5" />
    </button>

    <div
        x-ref="viewport"
        class="@container overflow-hidden select-none"
        x-on:pointerdown="onPointerDown($event)"
        x-on:pointermove="onPointerMove($event)"
        x-on:pointerup="onPointerUp($event)"
        x-on:pointercancel="onPointerUp($event)"
        x-on:click.capture="onClickCapture($event)"
    >
        <div
            x-ref="track"
            class="flex cursor-grab gap-4 touch-pan-y active:cursor-grabbing"
            x-bind:style="`transform: translate3d(${offset}px, 0, 0); transition: ${motion ? 'transform 450ms ease' : 'none'};`"
            x-on:transitionend="normalize($event)"
        >
            {{ $slot }}
        </div>
    </div>
</div>
