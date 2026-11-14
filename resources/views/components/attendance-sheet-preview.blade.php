{{--
    A small rendering of the blank attendance sheet for its settings page. It reads the sections and entries picked in
    the page's form straight from $wire, so it follows every change without a request. It mirrors the layout of
    pdf/attendance-sheets.blade.php without its exact geometry: the observation lines fill whatever room is left.
--}}
@use('App\Enums\AppointmentMode')
@use('App\Enums\AttendanceSheetSection')
@use('App\Models\AppointmentRecord')

@props([
    'team',
    'catalogEntries',
])

@php
    $entries = collect($catalogEntries)
        ->map(fn ($sectionEntries) => $sectionEntries->map(fn ($entry): array => ['id' => (string) $entry->id, 'name' => $entry->name])->values())
        ->all();
    $sheetDay = today();
    $paper = 'relative flex h-[472px] w-[333px] shrink-0 flex-col overflow-hidden rounded-[2px] bg-white px-[25px] py-[22px] font-[DejaVu_Sans,Verdana,sans-serif] text-[6.5px] leading-[1.25] text-[#141414] shadow-[0_1px_2px_rgb(0_0_0/0.08),0_0_0_1px_rgb(0_0_0/0.06)]';
    $label = 'text-[6px] font-bold uppercase tracking-[0.3px] text-[#333]';
    $sectionLabel = $label.' mt-[9px] mb-[3px]';
    $box = 'inline-block size-[7px] shrink-0 rounded-[1px] border-[0.8px] border-[#141414]';
    $blank = 'inline-block h-[7px] border-b-[0.6px] border-[#bdbdbd] align-[-1px]';
    $fill = 'h-[8px] flex-1 border-b-[0.6px] border-[#bdbdbd]';
    $guidances = AttendanceSheetSection::Guidances;
    $passes = AttendanceSheetSection::Passes;
    $back = AttendanceSheetSection::FluidicRemedies;
@endphp

{{-- Turning the back on shows it, so the person sees the page they just added. --}}
<aside
    {{ $attributes->class('min-w-0') }}
    aria-label="{{ __('Preview of the blank attendance sheet') }}"
    x-data="{
        page: 'front',
        entries: {{ \Illuminate\Support\Js::from($entries) }},
        printedEntries(section) {
            const pickedIds = this.$wire.sections[section] ? (this.$wire.printedEntryIds[section] ?? []) : [];

            return (this.entries[section] ?? []).filter((entry) => pickedIds.includes(entry.id));
        },
    }"
    x-init="$watch('$wire.sections.{{ $back->value }}', (printsBack) => printsBack && (page = 'back'))"
    data-test="attendance-sheet-preview"
>
    <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
        <div>
            <flux:heading>{{ __('Preview') }}</flux:heading>
            <flux:text class="mt-0.5">{{ __('Blank sheet, A5 page') }}</flux:text>
        </div>

        <flux:radio.group variant="segmented" size="sm" x-model="page" data-test="attendance-sheet-preview-page">
            <flux:radio value="front">{{ __('Front of the sheet') }}</flux:radio>
            <flux:radio value="back">{{ __('Back of the sheet') }}</flux:radio>
        </flux:radio.group>
    </div>

    <div class="flex overflow-x-auto rounded-xl border border-zinc-800/10 bg-zinc-50 p-3 dark:border-white/10 dark:bg-zinc-900">
        {{-- Front --}}
        <div x-show="page === 'front'" class="{{ $paper }} mx-auto" aria-hidden="true">
            <div class="flex items-center gap-[6px]">
                @if ($team->logo_url)
                    <img src="{{ $team->logo_url }}" alt="" class="max-h-[35px] max-w-[72px] shrink-0">
                @endif
                <div class="min-w-0 flex-1">
                    <div class="text-[8px] font-bold uppercase">{{ $team->display_legal_name }}</div>
                    @if ($team->address_summary)
                        <div class="mt-[2px] text-[6px] text-[#333]">{{ $team->address_summary }}</div>
                    @endif
                    @if ($team->formatted_phone)
                        <div class="mt-[2px] text-[6px] text-[#333]">{{ $team->formatted_phone }}</div>
                    @endif
                </div>
                <div class="w-[58px] shrink-0 text-center">
                    <div class="rounded-[1.5px] border-[1.1px] border-[#141414] px-[2px] py-[3px] text-[5.5px] font-bold uppercase leading-[1.2] tracking-[0.3px]">{{ trans_choice('Attendance sheet|Attendance sheets', 1) }}</div>
                    <div class="mt-[2px] text-[6px] text-[#333]">{{ $sheetDay->format('d/m/Y') }}</div>
                </div>
            </div>

            <div class="mt-[7px] mb-[8px] border-t-[1.6px] border-[#141414]"></div>

            <div class="mb-[5px] flex items-end gap-[4px] border-b-[0.8px] border-[#141414] pb-[2px]">
                <span class="{{ $label }}">{{ __('Name') }}:</span>
                <span class="flex-1 text-[7.5px] italic text-[#9a9a9a]">{{ __('Name of the assisted person') }}</span>
                <span class="{{ $label }}">{{ __('Age') }}:</span>
                <span class="w-[40px]"></span>
            </div>
            <div class="mb-[5px] flex items-end gap-[4px] border-b-[0.8px] border-[#141414] pb-[2px]">
                <span class="{{ $label }}">{{ __('Address') }}:</span>
            </div>

            {{-- More than two guidances split in two columns, filled top to bottom, as the printed sheet does. --}}
            <template x-if="printedEntries('{{ $guidances->value }}').length > 0">
                <div>
                    <div class="{{ $sectionLabel }}">{{ $guidances->label() }}</div>
                    <div x-bind:class="printedEntries('{{ $guidances->value }}').length > 2 && 'columns-2 gap-x-[7px]'">
                        <template x-for="guidance in printedEntries('{{ $guidances->value }}')" x-bind:key="guidance.id">
                            <div class="mb-[4.5px] flex break-inside-avoid items-start gap-[5px]">
                                <span class="{{ $box }}"></span>
                                <span x-text="guidance.name"></span>
                            </div>
                        </template>
                    </div>
                </div>
            </template>

            <template x-if="printedEntries('{{ $passes->value }}').length > 0">
                <div class="mt-[5px] border-b-[0.8px] border-[#141414]">
                    <div class="grid grid-cols-[minmax(0,1fr)_38px_45px_45px] items-center border-b-[0.8px] border-[#141414] pb-[3px] text-center text-[5.5px] font-bold text-[#333]">
                        <span class="{{ $label }} text-left">{{ $passes->label() }}</span>
                        <span>{{ __('Qty.') }}</span>
                        @foreach (AppointmentMode::cases() as $mode)
                            <span>{{ $mode->label() }}</span>
                        @endforeach
                    </div>
                    <div class="divide-y-[0.5px] divide-[#bdbdbd]">
                    <template x-for="pass in printedEntries('{{ $passes->value }}')" x-bind:key="pass.id">
                        <div class="grid h-[20px] grid-cols-[minmax(0,1fr)_38px_45px_45px] items-center text-center">
                            <span class="text-left" x-text="pass.name"></span>
                            <span><span class="{{ $blank }} w-[19px] border-[#141414]"></span></span>
                            @foreach (AppointmentMode::cases() as $mode)
                                <span><span class="inline-block size-[7px] rounded-full border-[0.8px] border-[#141414]"></span></span>
                            @endforeach
                        </div>
                    </template>
                    </div>
                </div>
            </template>

            <div x-show="$wire.sections.{{ AttendanceSheetSection::Infiltration->value }}">
                <div class="{{ $sectionLabel }}">{{ AttendanceSheetSection::Infiltration->label() }}</div>
                <div class="mb-[5px] flex items-end gap-[4px]">
                    <span>{{ __('Site or reason') }}:</span>
                    <span class="{{ $fill }}"></span>
                </div>
                <div class="mb-[4px]">
                    {{ __('Remove on day') }}:
                    <span class="{{ $blank }} w-[11px]"></span>/<span class="{{ $blank }} w-[11px]"></span>/<span class="{{ $blank }} w-[18px]"></span>
                    {{ __('at') }} <span class="{{ $blank }} w-[11px]"></span>:<span class="{{ $blank }} w-[11px]"></span>
                </div>
            </div>

            <div x-show="$wire.sections.{{ AttendanceSheetSection::Return->value }}">
                <div class="{{ $sectionLabel }}">{{ AttendanceSheetSection::Return->label() }}</div>
                <div class="mb-[5px] flex flex-wrap gap-x-[11px]">
                    @foreach (AppointmentRecord::RETURN_INTERVALS_DAYS as $days)
                        <span class="flex items-center gap-[4px]"><span class="{{ $box }}"></span>{{ __('+:days days', ['days' => $days]) }}</span>
                    @endforeach
                </div>
                <div class="mb-[4px] flex items-end gap-[4px]">
                    <span class="{{ $box }}"></span>
                    <span>{{ __('Other date') }}:</span>
                    <span class="{{ $fill }}"></span>
                    <span>{{ __('at') }} <span class="{{ $blank }} w-[11px]"></span>:<span class="{{ $blank }} w-[11px]"></span></span>
                </div>
            </div>

            <div x-show="$wire.sections.{{ AttendanceSheetSection::Observations->value }}" class="flex min-h-0 flex-1 flex-col">
                <div class="{{ $sectionLabel }}">{{ AttendanceSheetSection::Observations->label() }}</div>
                <div class="min-h-0 flex-1" style="background-image: repeating-linear-gradient(to bottom, transparent 0, transparent 13px, #bdbdbd 13px, #bdbdbd 13.6px); margin-bottom: 8px;"></div>
            </div>

            <div class="mt-auto flex items-end justify-between gap-[8px]">
                <div>
                    <div>{{ $sheetDay->translatedFormat('d \d\e F \d\e Y') }}</div>
                    @if ($team->address_city_line)
                        <div class="text-[#333]">{{ $team->address_city_line }}</div>
                    @endif
                </div>
                <div class="w-[126px] text-center">
                    <div class="h-[18px] border-b-[0.8px] border-[#141414]"></div>
                    <div class="pt-[2px] text-[#333]">{{ __('Mentor signature') }}</div>
                </div>
            </div>
        </div>

        {{-- Back --}}
        <div x-show="page === 'back' && $wire.sections.{{ $back->value }}" x-cloak class="{{ $paper }} mx-auto" aria-hidden="true">
            <div class="text-[9px] font-bold uppercase tracking-[0.6px]">{{ __('Fluidic remedies') }}</div>
            <div class="mt-[2px] text-[#333]"><span class="italic text-[#9a9a9a]">{{ __('Name of the assisted person') }}</span> · {{ $sheetDay->translatedFormat('d \d\e F \d\e Y') }}</div>
            <div class="mt-[7px] border-t-[1.6px] border-[#141414]"></div>
            @for ($line = 0; $line < 20; $line++)
                <div class="h-[18px] shrink-0 border-b-[0.6px] border-[#bdbdbd]"></div>
            @endfor
            <div class="absolute bottom-[22px] left-[25px] text-[6px] text-[#555]">{{ $team->display_legal_name }}</div>
        </div>

        <div x-show="page === 'back' && ! $wire.sections.{{ $back->value }}" x-cloak class="mx-auto flex h-[472px] w-[333px] shrink-0 flex-col items-center justify-center gap-1.5 rounded-sm border-[1.5px] border-dashed border-zinc-300 p-8 text-center dark:border-white/20" data-test="attendance-sheet-preview-no-back">
            <flux:icon.document-plus class="size-5 text-zinc-400 dark:text-zinc-500" />
            <flux:heading>{{ __('No back') }}</flux:heading>
            <flux:text>{{ __('Turn on “:section” to add this page.', ['section' => $back->label()]) }}</flux:text>
        </div>
    </div>

    <flux:text x-show="page === 'front' || $wire.sections.{{ $back->value }}" class="mt-2.5 flex items-start gap-1.5">
        <flux:icon.information-circle class="mt-0.5 size-4 shrink-0 text-zinc-400 dark:text-zinc-500" />
        <span x-show="page === 'front' && $wire.sections.{{ AttendanceSheetSection::Observations->value }}" x-cloak>{{ __('The observation lines take the room left at the bottom.') }}</span>
        <span x-show="page === 'front' && ! $wire.sections.{{ AttendanceSheetSection::Observations->value }}" x-cloak>{{ __('Without observations, the bottom of the front is left blank.') }}</span>
        <span x-show="page === 'back'" x-cloak>{{ $back->description() }}</span>
    </flux:text>
</aside>
