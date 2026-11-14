<?php

use App\Actions\Appointments\UpdateAttendanceSheetSettings;
use App\Enums\AttendanceSheetSection;
use App\Models\Team;
use App\Rules\AttendanceSheetSettingsRules;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    /**
     * Whether each section is printed, keyed by the section's value.
     *
     * @var array<string, bool>
     */
    public array $sections = [];

    /**
     * The ids, as the checkboxes hold them, of the catalog entries printed in each section that lists a catalog.
     *
     * @var array<string, array<int, string>>
     */
    public array $printedEntryIds = [];

    public function mount(): void
    {
        $team = $this->team();

        Gate::authorize('update', $team);

        foreach (AttendanceSheetSection::cases() as $section) {
            $this->sections[$section->value] = $team->printsOnAttendanceSheet($section);
        }

        foreach ($this->catalogEntries as $sectionValue => $entries) {
            $this->printedEntryIds[$sectionValue] = $entries->where('printed_on_attendance_sheet', true)
                ->map(fn ($entry): string => (string) $entry->id)
                ->values()
                ->all();
        }
    }

    public function saveSettings(UpdateAttendanceSheetSettings $updateAttendanceSheetSettings): void
    {
        $team = $this->team();

        Gate::authorize('update', $team);

        $validated = $this->validate(AttendanceSheetSettingsRules::all($team));

        $updateAttendanceSheetSettings->handle($team, [
            'hidden_sections' => array_map(AttendanceSheetSection::from(...), array_keys($validated['sections'], false)),
            'printed_entry_ids' => $validated['printedEntryIds'] ?? [],
        ]);

        Flux::toast(variant: 'success', text: __('Attendance sheet updated.'));
    }

    /**
     * Get the team's entries of each catalog the sheet lists, in the order the sheet lists them, keyed by the section's value.
     *
     * @return array<string, Collection<int, \App\Models\Guidance|\App\Models\PassType>>
     */
    #[Computed]
    public function catalogEntries(): array
    {
        $entries = [];

        foreach (AttendanceSheetSection::withCatalog() as $section) {
            $entries[$section->value] = $this->team()->{$section->catalog()->relationName()}()
                ->orderBy('name')
                ->get(['id', 'name', 'printed_on_attendance_sheet']);
        }

        return $entries;
    }

    /**
     * Get the current team, whose sheet is configured and whose details head the preview.
     */
    #[Computed]
    public function team(): Team
    {
        return Auth::user()->currentTeam;
    }

    public function render()
    {
        return $this->view()->title(__('Attendance sheet'));
    }
}; ?>

<section class="w-full">
    @include('partials.team-settings-heading')

    <x-pages::team-settings.layout :heading="__('Attendance sheet')" :subheading="__('What the blank sheet brings when printed. Sheets of completed appointments always show everything recorded.')">
        <form wire:submit="saveSettings">
            @php
                $sheetPages = [
                    'front' => ['legend' => __('Front of the sheet'), 'description' => __('The first page, filled in at the reception and during the attendance.'), 'sections' => AttendanceSheetSection::onFront()],
                    'back' => ['legend' => __('Back of the sheet'), 'description' => __('A second page, printed behind the front.'), 'sections' => [AttendanceSheetSection::FluidicRemedies]],
                ];
            @endphp

            @foreach ($sheetPages as $pageKey => $sheetPage)
                @unless ($loop->first)
                    <flux:separator class="mt-2 mb-6" />
                @endunless

                <flux:fieldset data-test="attendance-sheet-{{ $pageKey }}-group">
                    <flux:legend class="text-sm!">{{ $sheetPage['legend'] }}</flux:legend>
                    <flux:description>{{ $sheetPage['description'] }}</flux:description>

                    <div class="mt-2">
                        @foreach ($sheetPage['sections'] as $section)
                            @php
                                $catalog = $section->catalog();
                            @endphp

                            @unless ($loop->first)
                                <flux:separator variant="subtle" />
                            @endunless

                            <div class="py-4">
                                <flux:switch
                                    wire:model="sections.{{ $section->value }}"
                                    :label="$section->label()"
                                    :description="$section->description()"
                                    align="left"
                                    data-test="attendance-sheet-section-{{ $section->value }}"
                                />

                                @if ($catalog)
                                    @php
                                        $entries = $this->catalogEntries[$section->value];
                                        $pickedEntries = "\$wire.printedEntryIds.{$section->value}";
                                    @endphp

                                    <div x-show="$wire.sections.{{ $section->value }}" @unless ($this->sections[$section->value]) x-cloak @endunless class="mt-3 ms-11">
                                        @if ($entries->isEmpty())
                                            <flux:text>
                                                {{ __('Nothing registered yet.') }}
                                                <flux:link :href="$catalog->url()" wire:navigate data-test="attendance-sheet-{{ $section->value }}-register-link">{{ $catalog->newEntryTitle() }}</flux:link>
                                            </flux:text>
                                        @else
                                            <div class="rounded-xl border border-zinc-200 px-4 dark:border-white/10">
                                                <flux:checkbox.group wire:model="printedEntryIds.{{ $section->value }}" data-test="attendance-sheet-{{ $section->value }}-entries">
                                                    <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-2 py-3">
                                                        <flux:checkbox.all :label="$section->allEntriesLabel()" data-test="attendance-sheet-{{ $section->value }}-all" />

                                                        <div class="flex items-center gap-3">
                                                            <flux:badge
                                                                size="sm"
                                                                class="tabular-nums"
                                                                x-text="{{ \Illuminate\Support\Js::from(__(':count of :total')) }}.replace(':count', {{ $pickedEntries }}.length).replace(':total', {{ $entries->count() }})"
                                                                data-test="attendance-sheet-{{ $section->value }}-count"
                                                            >{{ __(':count of :total', ['count' => count($this->printedEntryIds[$section->value] ?? []), 'total' => $entries->count()]) }}</flux:badge>
                                                            <flux:link
                                                                :href="$catalog->url()"
                                                                wire:navigate
                                                                class="text-sm"
                                                                aria-label="{{ __('Manage :catalog', ['catalog' => Str::lower($catalog->label())]) }}"
                                                                data-test="attendance-sheet-{{ $section->value }}-manage-link"
                                                            >{{ __('Manage') }}</flux:link>
                                                        </div>
                                                    </div>

                                                    <flux:separator variant="subtle" />

                                                    <div class="space-y-3 py-3">
                                                        @foreach ($entries as $entry)
                                                            <flux:checkbox :value="(string) $entry->id" :label="$entry->name" wire:key="{{ $section->value }}-{{ $entry->id }}" data-test="attendance-sheet-{{ $section->value }}-entry" />
                                                        @endforeach
                                                    </div>
                                                </flux:checkbox.group>
                                            </div>

                                            <div x-show="{{ $pickedEntries }}.length === 0" @if (count($this->printedEntryIds[$section->value] ?? []) > 0) x-cloak @endif data-test="attendance-sheet-{{ $section->value }}-none-picked">
                                                <flux:text class="mt-3 flex items-start gap-1.5">
                                                    <flux:icon.information-circle class="mt-0.5 size-4 shrink-0 text-zinc-400 dark:text-zinc-500" />
                                                    {{ __('No entry picked. The section is left off the sheet.') }}
                                                </flux:text>
                                            </div>

                                            <flux:error :name="'printedEntryIds.'.$section->value" />
                                        @endif
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </flux:fieldset>
            @endforeach

            {{-- Double-sided printing pairs each front with the page after it, so the advice follows whether the back is printed. --}}
            <div x-show="$wire.sections.{{ AttendanceSheetSection::FluidicRemedies->value }}" @unless ($this->sections[AttendanceSheetSection::FluidicRemedies->value]) x-cloak @endunless data-test="attendance-sheet-double-sided-hint">
                <flux:callout variant="secondary" icon="printer" icon:variant="outline" :heading="__('Print the batch double-sided')" :text="__('Each sheet takes both sides of a page.')" />
            </div>
            <div x-show="! $wire.sections.{{ AttendanceSheetSection::FluidicRemedies->value }}" @if ($this->sections[AttendanceSheetSection::FluidicRemedies->value]) x-cloak @endif data-test="attendance-sheet-single-sided-warning">
                <flux:callout variant="warning" icon="exclamation-triangle" icon:variant="outline" :heading="__('Do not print the batch double-sided')" :text="__('Without the back, each sheet takes a single page. Double-sided, the next sheet would print behind this one.')" />
            </div>

            <div class="sticky bottom-0 z-10 mt-6 flex flex-col-reverse gap-2 border-t border-zinc-200 bg-white py-3 sm:flex-row sm:items-center sm:justify-end dark:border-white/10 dark:bg-zinc-800">
                <flux:text wire:dirty.flex class="flex items-center gap-2 sm:me-auto" data-test="attendance-sheet-unsaved-changes">
                    <span class="size-2 shrink-0 rounded-full bg-amber-500" aria-hidden="true"></span>
                    {{ __('Unsaved changes') }}
                </flux:text>

                <flux:button variant="primary" type="submit" wire:loading.attr="disabled" data-test="attendance-sheet-save-button">{{ __('Save') }}</flux:button>
            </div>
        </form>

        <x-slot:aside>
            <x-attendance-sheet-preview :team="$this->team" :catalog-entries="$this->catalogEntries" class="@3xl:sticky @3xl:top-6" />
        </x-slot:aside>
    </x-pages::team-settings.layout>
</section>
