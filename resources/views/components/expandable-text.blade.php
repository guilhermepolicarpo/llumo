{{-- Shows a long text as a two-line summary, with a link that smoothly reveals the rest; short texts show whole. --}}
{{-- While collapsed the text is line-clamped (for the ellipsis); toggling animates max-height between the clamped and the full height. --}}
<div
    x-data="{
        expanded: false,
        clamped: true,
        overflows: false,
        collapsedHeight: 0,
        measure() {
            this.collapsedHeight = this.$refs.text.clientHeight;
            this.overflows = this.$refs.text.scrollHeight > this.collapsedHeight + 1;
        },
        toggle() {
            const box = this.$refs.box;

            if (! this.expanded) {
                box.style.maxHeight = this.collapsedHeight + 'px';
                this.clamped = false;
                this.expanded = true;
                this.$nextTick(() => requestAnimationFrame(() => box.style.maxHeight = this.$refs.text.scrollHeight + 'px'));
            } else {
                box.style.maxHeight = this.$refs.text.scrollHeight + 'px';
                this.expanded = false;
                requestAnimationFrame(() => requestAnimationFrame(() => box.style.maxHeight = this.collapsedHeight + 'px'));
            }
        },
        settle() {
            if (! this.expanded) {
                this.clamped = true;
            }

            this.$refs.box.style.maxHeight = '';
        },
    }"
    x-init="$nextTick(() => measure())"
    x-resize="if (! expanded) measure()"
    {{ $attributes }}
>
    <div
        x-ref="box"
        x-on:transitionend.self="settle()"
        class="overflow-hidden transition-[max-height] duration-300 ease-in-out motion-reduce:transition-none"
    >
        <div x-ref="text" x-bind:class="! clamped && 'line-clamp-none'" class="line-clamp-2">
            {{ $slot }}
        </div>
    </div>

    <button
        type="button"
        x-show="overflows || expanded"
        style="display: none"
        x-on:click="toggle()"
        x-bind:aria-expanded="expanded"
        class="mt-1 text-sm font-medium text-(--color-accent-content) hover:underline"
        data-test="expandable-text-toggle"
    >
        <span x-text="expanded ? @js(__('See less')) : @js(__('See more'))">{{ __('See more') }}</span>
    </button>
</div>
