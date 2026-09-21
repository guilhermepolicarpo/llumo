<x-layouts::app.sidebar :title="$title ?? null">
    <flux:main wire:transition.navigate>
        {{ $slot }}
    </flux:main>
</x-layouts::app.sidebar>
