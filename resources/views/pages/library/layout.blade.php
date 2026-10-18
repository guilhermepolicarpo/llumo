@use('App\Enums\LibraryCatalog')

{{-- The catalog layout, listing the library's catalogs and its loan rules. --}}
<x-pages::catalogs.layout :heading="$heading ?? null" :subheading="$subheading ?? null">
    <x-slot:nav>
        <flux:navlist aria-label="{{ __('Library settings') }}">
            @foreach (LibraryCatalog::cases() as $catalog)
                <flux:navlist.item :href="$catalog->url()" wire:navigate data-test="library-nav-{{ $catalog->value }}">{{ $catalog->label() }}</flux:navlist.item>
            @endforeach
            <flux:navlist.item :href="route('library.settings')" wire:navigate data-test="library-nav-settings">{{ __('Loan rules') }}</flux:navlist.item>
        </flux:navlist>
    </x-slot:nav>

    @isset($actions)
        <x-slot:actions>{{ $actions }}</x-slot:actions>
    @endisset

    {{ $slot }}
</x-pages::catalogs.layout>
