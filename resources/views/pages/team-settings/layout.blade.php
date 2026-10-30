{{-- The two-column shell of the Spiritist Center settings: the grouped navigation of its pages beside the page content. --}}
@use('App\Enums\Catalog')
@use('App\Enums\LibraryCatalog')

<div class="flex items-start max-md:flex-col">
    <div class="me-10 w-full pb-4 md:w-55">
        <flux:navlist aria-label="{{ __('Spiritist Center settings') }}">
            <flux:navlist.group :heading="__('Center')" class="grid">
                <flux:navlist.item :href="route('team-settings.profile')" wire:navigate data-test="team-settings-nav-profile">{{ __('Center details') }}</flux:navlist.item>
                <flux:navlist.item :href="route('team-settings.members')" wire:navigate data-test="team-settings-nav-members">{{ __('Members') }}</flux:navlist.item>
            </flux:navlist.group>

            <flux:navlist.group :heading="__('Attendance')" class="grid mt-2">
                <flux:navlist.item :href="route('team-settings.appointment-types')" wire:navigate data-test="team-settings-nav-appointment-types">{{ __('Appointment types') }}</flux:navlist.item>
                @foreach (Catalog::cases() as $catalog)
                    <flux:navlist.item :href="$catalog->url()" wire:navigate data-test="team-settings-nav-{{ $catalog->value }}">{{ $catalog->label() }}</flux:navlist.item>
                @endforeach
            </flux:navlist.group>

            <flux:navlist.group :heading="__('Library')" class="grid mt-2">
                @foreach (LibraryCatalog::cases() as $catalog)
                    <flux:navlist.item :href="$catalog->url()" wire:navigate data-test="team-settings-nav-{{ $catalog->value }}">{{ $catalog->label() }}</flux:navlist.item>
                @endforeach
                <flux:navlist.item :href="route('team-settings.loan-rules')" wire:navigate data-test="team-settings-nav-loan-rules">{{ __('Loan rules') }}</flux:navlist.item>
            </flux:navlist.group>
        </flux:navlist>
    </div>

    <flux:separator class="md:hidden" />

    <div class="min-w-0 flex-1 self-stretch max-md:pt-6">
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
