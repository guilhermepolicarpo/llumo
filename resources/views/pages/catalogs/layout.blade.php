@use('App\Enums\Catalog')

<div class="flex items-start max-md:flex-col">
    <div class="me-10 w-full pb-4 md:w-[220px]">
        <flux:navlist aria-label="{{ __('Catalogs') }}">
            <flux:navlist.item :href="route('catalogs.appointment-types')" wire:navigate data-test="catalogs-nav-appointment-types">{{ __('Appointment types') }}</flux:navlist.item>
            @foreach (Catalog::cases() as $catalog)
                <flux:navlist.item :href="route('catalogs.index', ['catalog' => $catalog])" wire:navigate data-test="catalogs-nav-{{ $catalog->value }}">{{ $catalog->label() }}</flux:navlist.item>
            @endforeach
        </flux:navlist>
    </div>

    <flux:separator class="md:hidden" />

    <div class="flex-1 self-stretch max-md:pt-6">
        <div class="w-full max-w-2xl">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    @if ($heading ?? false)
                        <flux:heading>{{ $heading }}</flux:heading>
                    @endif

                    @if ($subheading ?? false)
                        <flux:subheading>{{ $subheading }}</flux:subheading>
                    @endif
                </div>

                {{ $actions ?? '' }}
            </div>

            <div class="mt-5">
                {{ $slot }}
            </div>
        </div>
    </div>
</div>
