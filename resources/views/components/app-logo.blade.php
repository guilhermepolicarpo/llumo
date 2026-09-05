@props([
    'sidebar' => false,
])
{{-- fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6" --}}
@if($sidebar)
    <flux:sidebar.brand :name="config('app.name', 'Laravel')" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-md bg-accent-content text-accent-foreground">
            <x-app-logo-icon class="size-5 fill-none text-white dark:text-black stroke-current" />
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand :name="config('app.name', 'Laravel')" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-md bg-accent-content text-accent-foreground">
            <x-app-logo-icon class="size-5 fill-none text-white dark:text-black stroke-current" />
        </x-slot>
    </flux:brand>
@endif
