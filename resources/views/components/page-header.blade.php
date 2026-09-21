{{-- The default slot holds optional actions shown beside the heading. --}}
@props([
    'heading',
    'subheading' => null,
    'backHref' => null,
    'backLabel' => null,
])

<div {{ $attributes }}>
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-start gap-2">
            @if ($backHref)
                <flux:button variant="ghost" size="sm" icon="arrow-left" :href="$backHref" wire:navigate :aria-label="$backLabel ?? __('Back')" data-test="page-header-back-button" />
            @endif

            <div>
                <flux:heading size="xl" level="1">{{ $heading }}</flux:heading>
                @if ($subheading)
                    <flux:subheading>{{ $subheading }}</flux:subheading>
                @endif
            </div>
        </div>

        {{ $slot }}
    </div>

    <flux:separator variant="subtle" class="my-4" />
</div>
