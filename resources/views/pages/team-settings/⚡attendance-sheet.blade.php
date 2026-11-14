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

    private function team(): Team
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
        <form wire:submit="saveSettings" class="space-y-6">
            <div class="space-y-5">
                @foreach (AttendanceSheetSection::cases() as $section)
                    @php
                        $catalog = $section->catalog();
                    @endphp

                    @unless ($loop->first)
                        <flux:separator variant="subtle" />
                    @endunless

                    <div>
                        <flux:checkbox
                            wire:model="sections.{{ $section->value }}"
                            :label="$section->label()"
                            :description="$section->description()"
                            data-test="attendance-sheet-section-{{ $section->value }}"
                        />

                        @if ($catalog)
                            <div x-show="$wire.sections.{{ $section->value }}" @unless ($this->sections[$section->value]) x-cloak @endunless class="mt-4 ms-7">
                                @if ($this->catalogEntries[$section->value]->isEmpty())
                                    <flux:text>
                                        {{ __('Nothing registered yet.') }}
                                        <flux:link :href="$catalog->url()" wire:navigate data-test="attendance-sheet-{{ $section->value }}-register-link">{{ $catalog->newEntryTitle() }}</flux:link>
                                    </flux:text>
                                @else
                                    <flux:checkbox.group wire:model="printedEntryIds.{{ $section->value }}" data-test="attendance-sheet-{{ $section->value }}-entries">
                                        <flux:checkbox.all :label="$section->allEntriesLabel()" data-test="attendance-sheet-{{ $section->value }}-all" />
                                        @foreach ($this->catalogEntries[$section->value] as $entry)
                                            <flux:checkbox :value="(string) $entry->id" :label="$entry->name" wire:key="{{ $section->value }}-{{ $entry->id }}" data-test="attendance-sheet-{{ $section->value }}-entry" />
                                        @endforeach
                                    </flux:checkbox.group>
                                    <flux:error :name="'printedEntryIds.'.$section->value" />
                                @endif
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            <flux:button variant="primary" type="submit" wire:loading.attr="disabled" data-test="attendance-sheet-save-button">{{ __('Save') }}</flux:button>
        </form>
    </x-pages::team-settings.layout>
</section>
