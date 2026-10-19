{{-- A name an ISBN lookup found for a single-choice catalog the library does not have yet, with the actions to add or ignore it. --}}
@props([
    'name',
    'add',
    'dismiss',
    'testId',
])

<div class="mt-2 flex flex-wrap items-center gap-2" data-test="{{ $testId }}">
    <flux:text class="text-sm">{{ __('Found: :name', ['name' => $name]) }}</flux:text>
    <flux:button size="xs" icon="plus" icon:variant="outline" wire:click="{{ $add }}" data-test="{{ $testId }}-add">{{ __('Add') }}</flux:button>
    <flux:button size="xs" variant="ghost" icon="x-mark" icon:variant="outline" wire:click="{{ $dismiss }}" :aria-label="__('Ignore :name', ['name' => $name])" data-test="{{ $testId }}-dismiss" />
</div>
