{{-- Shows a long text collapsed to a few lines with a fade, and smoothly reveals the rest; short texts show whole. --}}
{{-- The 160 below is the collapsed height of max-h-40. --}}
<div x-data="{ expanded: false, fullHeight: 0, get overflows() { return this.fullHeight > 160 } }" {{ $attributes }}>
    <div
        x-bind:style="expanded && { maxHeight: fullHeight + 'px' }"
        x-bind:class="overflows && ! expanded && 'mask-b-from-[calc(100%-4rem)]'"
        class="max-h-40 overflow-hidden transition-[max-height] duration-300 ease-in-out motion-reduce:transition-none"
        >
        <div x-resize="fullHeight = $height">
            {{ $slot }}
        </div>
    </div>

    <div x-show="overflows" style="display: none" class="mt-2 flex justify-center">
        <flux:button
            size="xs"
            class="rounded-full! shadow-sm"
            x-on:click="expanded = ! expanded"
            x-bind:aria-expanded="expanded"
            data-test="expandable-text-toggle"
            >
            <span x-text="expanded ? @js(__('See less')) : @js(__('See more'))">{{ __('See more') }}</span>
            <flux:icon.chevron-down class="size-4 transition-transform duration-300 motion-reduce:transition-none" x-bind:class="expanded && 'rotate-180'" />
        </flux:button>
    </div>
</div>
