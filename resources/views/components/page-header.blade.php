{{-- The default slot holds optional actions shown beside the heading; the optional leading slot sits before it, such as an avatar. --}}
@props([
    'heading',
    'subheading' => null,
    'backHref' => null,
    'backLabel' => null,
    'separator' => true,
    'stackUntil' => 'sm',
])

@php
    $rowClasses = match ($stackUntil) {
        'lg' => 'lg:flex-row lg:items-center lg:justify-between',
        default => 'sm:flex-row sm:items-center sm:justify-between',
    };
@endphp

<div {{ $attributes->class('relative mb-6 w-full') }}>
    <div @class(['flex flex-col gap-4', $rowClasses])>
        <div class="flex items-start gap-2">
            @if ($backHref)
                <flux:button variant="ghost" size="sm" icon="arrow-left" :href="$backHref" wire:navigate :aria-label="$backLabel ?? __('Back')" data-test="page-header-back-button" />
            @endif

            @isset($leading)
                <div {{ $leading->attributes->class('shrink-0') }}>{{ $leading }}</div>
            @endisset

            <div class="min-w-0">
                <flux:heading size="xl" level="1">{{ $heading }}</flux:heading>
                @if ($subheading)
                    <flux:subheading size="lg">{{ $subheading }}</flux:subheading>
                @endif
            </div>
        </div>

        {{ $slot }}
    </div>

    @if ($separator)
        <flux:separator variant="subtle" class="mt-6" />
    @endif
</div>
