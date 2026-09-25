{{-- Lays out a flyout's content: only the body scrolls, the footer stays pinned, and a fade with a shortcut shows while more content sits below. --}}
{{-- The flyout modal must be a flex column (class="flex flex-col") for the body to fill it. --}}
@props(['moreLabel' => __('More details')])

<div
    x-data="{
        hasMoreBelow: false,
        measure() {
            const scroller = this.$refs.scroller;
            this.hasMoreBelow = scroller.scrollTop + scroller.clientHeight < scroller.scrollHeight - 1;
        },
    }"
    x-init="const observer = new ResizeObserver(() => measure()); observer.observe($refs.scroller); observer.observe($refs.scrollerContent)"
    {{ $attributes->class('flex min-h-0 flex-1 flex-col') }}
    >
    <div class="relative -mx-8 flex min-h-0 flex-1 flex-col">
        <div x-ref="scroller" x-on:scroll.passive="measure()" class="min-h-0 flex-1 overflow-y-auto px-8">
            <div x-ref="scrollerContent" class="space-y-6 pb-6">
                {{ $slot }}
            </div>
        </div>

        <div
            x-show="hasMoreBelow"
            x-transition.opacity
            style="display: none"
            class="pointer-events-none absolute inset-x-0 bottom-0 flex h-18 items-end justify-center bg-linear-to-b from-white/0 to-white pb-2.5 dark:from-zinc-800/0 dark:to-zinc-800"
            >
            <flux:button
                size="xs"
                icon:trailing="chevron-down"
                icon:variant="outline"
                class="pointer-events-auto rounded-full! shadow-sm"
                x-on:click="$refs.scroller.scrollTo({ top: $refs.scroller.scrollHeight, behavior: 'smooth' })"
                data-test="flyout-more-button"
                >
                {{ $moreLabel }}
            </flux:button>
        </div>
    </div>

    @isset($footer)
        <div {{ $footer->attributes->class('shrink-0 pt-5') }}>
            {{ $footer }}
        </div>
    @endisset
</div>
