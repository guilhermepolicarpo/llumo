<?php

use App\Actions\Appointments\PerformAppointmentAction;
use App\Actions\Appointments\SaveAppointmentRecord;
use App\Actions\Appointments\SaveAppointmentRecordDraft;
use App\Concerns\InteractsWithAppointmentActions;
use App\Concerns\InteractsWithAppointmentRecordForm;
use App\Enums\AppointmentAction;
use App\Enums\AppointmentStatus;
use App\Enums\Catalog;
use App\Enums\InfiltrationRemovalPlace;
use App\Models\Appointment;
use App\Models\AppointmentRecord;
use App\Models\User;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
{
    use InteractsWithAppointmentActions;
    use InteractsWithAppointmentRecordForm;

    /**
     * How many previous records the history block lists.
     */
    public const int HISTORY_LIMIT = 5;

    public Appointment $appointment;

    /**
     * When the current user opened the record, which becomes the start of the attendance if they take it over.
     */
    #[Locked]
    public CarbonImmutable $openedAt;

    public function mount(Appointment $appointment, PerformAppointmentAction $performAppointmentAction): void
    {
        Gate::authorize('view', [AppointmentRecord::class, $appointment]);

        $this->openedAt = now();

        if (AppointmentAction::Start->isAvailableFor($appointment) && Gate::allows('perform', [$appointment, AppointmentAction::Start]) && $performAppointmentAction->handle($appointment, AppointmentAction::Start, Auth::user())) {
            $appointment->refresh();
        }

        $this->appointment = $appointment;

        $this->fillRecordForm($this->record);

        if ($this->canUpdate) {
            $this->restoreDraft($this->record);
        }
    }

    /**
     * Save the record and keep attending.
     */
    public function save(SaveAppointmentRecord $saveAppointmentRecord): void
    {
        $this->persistRecord($saveAppointmentRecord);

        Flux::toast(variant: 'success', text: __('Record saved.'));
    }

    /**
     * Keep the remote pass dates chosen in the modal by saving the form as a draft, from which the remote pass sheet
     * can be printed right away, and tell the browser when the draft was saved.
     *
     * The mentor is the last thing filled in, so the record is not saved nor fully validated here: only the passes are.
     * The sheet reads the draft until the record is saved. Passes that do not validate throw before returning, and
     * their errors show on the form.
     */
    public function saveRemotePassDates(SaveAppointmentRecordDraft $saveAppointmentRecordDraft): string
    {
        Gate::authorize('update', [AppointmentRecord::class, $this->appointment]);

        $this->form->validatePassPrescriptions($this->appointment);

        return $this->keepDraft($saveAppointmentRecordDraft);
    }

    /**
     * Save the record and finish the appointment.
     *
     * The record is saved first so that whoever fills it in takes the appointment over from when they opened it.
     */
    public function complete(SaveAppointmentRecord $saveAppointmentRecord, PerformAppointmentAction $performAppointmentAction): void
    {
        Gate::authorize('perform', [$this->appointment, AppointmentAction::Complete]);

        $this->persistRecord($saveAppointmentRecord);

        if (! $performAppointmentAction->handle($this->appointment, AppointmentAction::Complete, Auth::user())) {
            Flux::toast(variant: 'danger', text: __('This action is no longer available for the appointment.'));

            return;
        }

        Flux::toast(variant: 'success', text: __('Appointment completed.'));

        $this->redirectRoute('appointments.index', navigate: true);
    }

    /**
     * Send the assisted person back to the waiting queue, keeping what was filled as a draft.
     */
    public function returnToQueue(PerformAppointmentAction $performAppointmentAction, SaveAppointmentRecordDraft $saveAppointmentRecordDraft): void
    {
        Gate::authorize('perform', [$this->appointment, AppointmentAction::ReturnToQueue]);

        $this->saveDraft($saveAppointmentRecordDraft);

        if (! $this->performAppointmentAction($performAppointmentAction, $this->appointment, AppointmentAction::ReturnToQueue)) {
            return;
        }

        $this->redirectRoute('appointments.index', navigate: true);
    }

    #[Computed]
    public function canUpdate(): bool
    {
        return Gate::allows('update', [AppointmentRecord::class, $this->appointment]);
    }

    /**
     * Determine whether the record could be changed if the appointment were not too old for the current user.
     */
    #[Computed]
    public function isLockedByAge(): bool
    {
        return $this->appointment->status->allowsRecordEditing() && ! $this->canUpdate;
    }

    #[Computed]
    public function isInProgress(): bool
    {
        return $this->appointment->status === AppointmentStatus::InProgress;
    }

    /**
     * Get the attendant of the appointment when it is someone other than the current user.
     */
    #[Computed]
    public function otherAttendant(): ?User
    {
        if (! $this->isInProgress || $this->appointment->attendant_id === null || $this->appointment->attendant_id === Auth::id()) {
            return null;
        }

        return $this->appointment->attendant;
    }

    #[Computed]
    public function record(): ?AppointmentRecord
    {
        return $this->appointment->record()
            ->with(['mentor', 'fluidicRemedies', 'guidances', 'passPrescriptions.passType', 'passPrescriptions.sessions', 'infiltrationRemovalAppointment.appointmentType', 'returnAppointment.appointmentType'])
            ->first();
    }

    /**
     * Get the latest records of the assisted person's completed appointments, of any type.
     *
     * @return Collection<int, AppointmentRecord>
     */
    #[Computed]
    public function history(): Collection
    {
        return $this->appointment->assistedPerson->completedAppointmentRecords()
            ->where('appointments.id', '!=', $this->appointment->id)
            ->with(['appointment.appointmentType', 'mentor', 'fluidicRemedies', 'guidances'])
            ->limit(self::HISTORY_LIMIT)
            ->get();
    }

    private function persistRecord(SaveAppointmentRecord $saveAppointmentRecord): void
    {
        Gate::authorize('update', [AppointmentRecord::class, $this->appointment]);

        $attributes = $this->form->validatedAttributes($this->appointment);

        $saveAppointmentRecord->handle($this->appointment, Auth::user(), $attributes, $this->openedAt);

        unset($this->record);
        $this->restoredDraft = null;
        $this->fillRecordForm($this->record);
    }

    public function render()
    {
        return $this->view()->title(__('Appointment record'));
    }
}; ?>

@php($person = $appointment->assistedPerson)

<section class="w-full max-w-6xl">
    <x-page-header
        :heading="__('Appointment record')"
        :subheading="$this->canUpdate ? __('Record what was done in this appointment') : __('See what was recorded in this appointment')"
        :back-href="route('appointments.index')"
        :back-label="__('Appointments')"
    >
        <div class="flex flex-wrap items-center gap-3">
            {{-- While attending, the record is filled right here: its sheet is only worth printing once completed. --}}
            @can('printAttendanceSheet', $appointment)
                @if ($appointment->filledRecord() !== null)
                    <flux:button
                        icon="printer"
                        icon:variant="outline"
                        :href="route('appointments.attendance-sheet', ['appointment' => $appointment])"
                        target="_blank"
                        data-test="appointment-record-print-button"
                        >
                        {{ __('Print attendance sheet') }}
                    </flux:button>
                @endif
            @endcan

            @can('printRemotePassSheet', $appointment)
                <flux:button
                    icon="printer"
                    icon:variant="outline"
                    :href="route('appointments.remote-pass-sheet', ['appointment' => $appointment])"
                    target="_blank"
                    data-test="appointment-record-print-remote-passes-button"
                    >
                    {{ __('Print remote passes') }}
                </flux:button>
            @endcan
        </div>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem] lg:items-start">
        {{-- Sticks to the top while it fits the viewport; when taller, it scrolls with the page and sticks by its bottom. --}}
        <aside
            x-data="{ top: 24, fit() { if (window.matchMedia('(min-width: 1024px)').matches) { this.top = Math.min(24, window.innerHeight - this.$el.offsetHeight - 24) } } }"
            x-init="fit()"
            x-resize="fit()"
            x-on:resize.window.throttle.100ms="fit()"
            x-bind:style="{ top: top + 'px' }"
            class="space-y-4 lg:sticky lg:order-last"
        >
            <flux:card class="space-y-4" data-test="appointment-record-header">
                <div class="flex items-center gap-3">
                    <div class="min-w-0">
                        <flux:heading size="lg" class="truncate">{{ $person->name }}</flux:heading>
                        @if ($person->formatted_age)
                            <flux:text>{{ $person->formatted_age }}</flux:text>
                        @endif
                    </div>
                </div>

                @if ($contactDetails = array_filter(['phone' => $person->formatted_phone, 'envelope' => $person->email, 'map-pin' => $person->formatted_address]))
                    <div class="space-y-2">
                        @foreach ($contactDetails as $icon => $detail)
                            <div class="flex items-start gap-2">
                                <flux:icon :name="$icon" class="mt-0.5 size-4 shrink-0 text-zinc-400 dark:text-zinc-500" />
                                <flux:text class="min-w-0 wrap-break-word">{{ $detail }}</flux:text>
                            </div>
                        @endforeach
                    </div>
                @endif

                <flux:separator variant="subtle" />

                <div class="space-y-1">
                    <div class="flex items-center justify-between gap-2">
                        <flux:heading>{{ $appointment->appointmentType->name }}</flux:heading>
                        <flux:badge size="sm" :color="$appointment->status->color()">{{ $appointment->status->label() }}</flux:badge>
                    </div>
                    <flux:text>{{ $appointment->mode->label() }} · {{ $appointment->scheduled_on->format('d/m/Y') }}</flux:text>
                </div>
            </flux:card>

            @if ($this->history->isNotEmpty())
                <div x-data="{ open: false }" class="rounded-lg border border-zinc-200 dark:border-white/10" data-test="appointment-record-history">
                    <button
                        type="button"
                        x-on:click="open = ! open"
                        x-bind:aria-expanded="open"
                        aria-controls="appointment-record-history-entries"
                        class="flex w-full cursor-pointer items-center gap-2 px-4 py-3 text-start text-sm font-medium text-zinc-800 dark:text-white"
                        data-test="appointment-record-history-toggle"
                    >
                        <flux:icon name="chevron-right" class="size-4 transition-transform duration-200" x-bind:class="open && 'rotate-90'" />
                        {{ __('Previous appointments (:count)', ['count' => $this->history->count()]) }}
                    </button>

                    <div id="appointment-record-history-entries" x-show="open" x-collapse x-cloak>
                        <div class="divide-y divide-zinc-100 border-t border-zinc-100 dark:divide-white/5 dark:border-white/5">
                            @foreach ($this->history as $previous)
                                <div class="space-y-1 px-4 py-3 text-sm" wire:key="history-{{ $previous->id }}" data-test="appointment-record-history-entry">
                                    <div class="font-medium text-zinc-900 dark:text-white">
                                        {{ $previous->appointment->scheduled_on->format('d/m/Y') }} · {{ $previous->appointment->appointmentType->name }}
                                        @if ($previous->mentor)
                                            <span class="font-normal text-zinc-500 dark:text-zinc-400">· {{ $previous->mentor->name }}</span>
                                        @endif
                                    </div>
                                    @if ($previous->fluidicRemedies->isNotEmpty())
                                        <div><span class="text-zinc-500 dark:text-zinc-400">{{ __('Fluidic remedies') }}:</span> {{ $previous->fluidicRemedies->pluck('name')->implode(', ') }}</div>
                                    @endif
                                    @if ($previous->guidances->isNotEmpty())
                                        <div>
                                            <span class="text-zinc-500 dark:text-zinc-400">{{ __('Guidances') }}:</span>
                                            {{ $previous->guidance_summary }}
                                        </div>
                                    @endif
                                    @if ($previous->return_on)
                                        <div><span class="text-zinc-500 dark:text-zinc-400">{{ __('Return date') }}:</span> {{ $previous->return_on->format('d/m/Y') }}</div>
                                    @endif
                                </div>
                            @endforeach
                        </div>

                        @unless ($person->trashed())
                            <div class="border-t border-zinc-100 px-4 py-2 dark:border-white/5">
                                <flux:button
                                    variant="ghost"
                                    size="xs"
                                    icon="arrow-top-right-on-square"
                                    icon:variant="outline"
                                    class="-ms-2"
                                    :href="route('assisted-people.show', ['assistedPerson' => $person])"
                                    wire:navigate
                                    data-test="appointment-record-full-history-link"
                                    >
                                    {{ __('View full history') }}
                                </flux:button>
                            </div>
                        @endunless
                    </div>
                </div>
            @endif
        </aside>

        <div class="min-w-0 space-y-6">
            @if ($this->otherAttendant)
                <flux:callout variant="warning" icon="exclamation-triangle" icon:variant="outline" :heading="__(':name is already attending this appointment.', ['name' => $this->otherAttendant->name])" data-test="appointment-record-other-attendant" />
            @endif

            @if ($restoredDraft)
                <flux:callout variant="secondary" icon="document-text" icon:variant="outline" inline data-test="appointment-record-restored-draft">
                    <flux:callout.heading>
                        {{ $restoredDraft['name']
                            ? __('We restored an unsaved draft from :time (:name).', $restoredDraft)
                            : __('We restored an unsaved draft from :time.', $restoredDraft) }}
                    </flux:callout.heading>

                    <x-slot name="actions" class="@md:self-center">
                        <flux:button size="sm" wire:click="discardDraft" data-test="appointment-record-discard-draft-button">
                            {{ __('Discard draft') }}
                        </flux:button>
                    </x-slot>
                </flux:callout>
            @endif

            @if ($this->canUpdate)
                <form
                    wire:submit="save"
                    class="space-y-8"
                    x-data="{
                        debounceMs: 3000,
                        maxWaitMs: 20000,
                        timer: null,
                        pendingSince: null,
                        status: '',
                        schedule() {
                            this.pendingSince ??= Date.now();
                            clearTimeout(this.timer);

                            const wait = Math.max(0, Math.min(this.debounceMs, this.pendingSince + this.maxWaitMs - Date.now()));

                            this.timer = setTimeout(() => this.flush(), wait);
                        },
                        cancel() {
                            clearTimeout(this.timer);
                            this.timer = null;
                            this.pendingSince = null;
                        },
                        flush() {
                            if (this.pendingSince === null) {
                                return;
                            }

                            this.cancel();

                            $wire.saveDraft().then((status) => this.status = status ?? this.status);
                        },
                    }"
                    x-on:input="schedule()"
                    x-on:change="schedule()"
                    x-on:catalog-picker-change="schedule()"
                    x-on:return-date-picked="schedule()"
                    x-on:remote-pass-dates-saving="cancel()"
                    x-on:remote-pass-dates-saved="status = $event.detail ?? status"
                    x-on:submit="cancel(); status = ''"
                    x-on:visibilitychange.document="document.hidden && flush()"
                    x-on:livewire:navigating.document="flush()"
                    data-test="appointment-record-form"
                >
                    <div class="space-y-4">
                        <flux:heading size="lg">{{ __('Fluidic remedy') }}</flux:heading>

                        <flux:field>
                            <flux:label>{{ __('Medications') }}</flux:label>
                            <x-catalog-picker :catalog="Catalog::FluidicRemedy" model="form.fluidicRemedyIds" :options="$this->fluidicRemedies" :selected="$form->fluidicRemedyIds" multiple test-id="appointment-record-fluidic-remedies" />
                            <flux:error name="form.fluidicRemedyIds" />
                        </flux:field>

                        <flux:input wire:model="form.fluidInstructions" :label="__('How to take')" :placeholder="__('E.g. 3 times a day, every 3 hours')" data-test="appointment-record-fluid-instructions-input" />
                    </div>

                    <flux:separator variant="subtle" />

                    <div class="space-y-4">
                        <flux:heading size="lg">{{ __('Guidances') }}</flux:heading>

                        <flux:field>
                            <flux:label>{{ __('Recommended guidances') }}</flux:label>
                            <x-catalog-picker
                                :catalog="Catalog::Guidance"
                                model="form.guidanceIds"
                                :options="$this->guidances"
                                :selected="$form->guidanceIds"
                                multiple
                                detail-model="form.guidanceDetails"
                                :detail-placeholder="__('Detail (optional)')"
                                test-id="appointment-record-guidances"
                            />
                        </flux:field>
                        <flux:error name="form.guidanceIds.*" />
                        <flux:error name="form.guidanceDetails.*" />
                    </div>

                    <flux:separator variant="subtle" />

                    <div class="space-y-4">
                        <flux:heading size="lg">{{ __('Passes') }}</flux:heading>

                        @foreach ($form->passPrescriptions as $index => $prescription)
                            <div class="space-y-2" wire:key="pass-prescription-{{ $index }}" data-test="appointment-record-pass-prescription">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                                    <flux:field class="min-w-0 flex-1">
                                        <flux:label>{{ __('Pass type') }}</flux:label>
                                        <x-catalog-picker :catalog="Catalog::PassType" model="form.passPrescriptions.{{ $index }}.pass_type_id" :options="$this->passTypes" :selected="$prescription['pass_type_id']" :index="$index" test-id="appointment-record-pass-type-{{ $index }}" />
                                    </flux:field>

                                    <div class="w-24">
                                        <flux:input type="number" min="1" max="99" wire:model="form.passPrescriptions.{{ $index }}.quantity" :label="__('Quantity')" data-test="appointment-record-pass-quantity-input" />
                                    </div>

                                    <flux:radio.group wire:model="form.passPrescriptions.{{ $index }}.mode" variant="segmented" :label="__('Mode')">
                                        @foreach ($this->modes as $modeOption)
                                            <flux:radio :value="$modeOption['value']" :label="$modeOption['label']" />
                                        @endforeach
                                    </flux:radio.group>

                                    <flux:button variant="ghost" size="sm" icon="x-mark" icon:variant="outline" class="self-end sm:mb-1.5" wire:click="removePassPrescription({{ $index }})" :aria-label="__('Remove pass')" data-test="appointment-record-remove-pass-button" />
                                </div>

                                <flux:error name="form.passPrescriptions.{{ $index }}.pass_type_id" />
                                <flux:error name="form.passPrescriptions.{{ $index }}.quantity" />
                                <flux:error name="form.passPrescriptions.{{ $index }}.mode" />
                                <flux:error name="form.passPrescriptions.{{ $index }}.dates" />
                                @foreach (array_keys($prescription['dates'] ?? []) as $dateIndex)
                                    <flux:error name="form.passPrescriptions.{{ $index }}.dates.{{ $dateIndex }}" />
                                @endforeach
                            </div>
                        @endforeach

                        {{-- The remote pass dates follow the rows as they are picked, in the browser, so the modal opens without a request. --}}
                        <div x-data="remotePassDates({{ \Illuminate\Support\Js::from($this->remotePassDatesConfig) }})" class="flex flex-wrap items-center gap-2">
                            <flux:button size="sm" icon="plus" icon:variant="outline" wire:click="addPassPrescription" data-test="appointment-record-add-pass-button">
                                {{ __('Add pass') }}
                            </flux:button>

                            <flux:modal.trigger name="remote-pass-dates" x-show="hasRemoteRows" x-cloak>
                                <flux:button size="sm" icon="calendar-days" icon:variant="outline" x-on:click="fill()" data-test="appointment-record-remote-pass-dates-button">
                                    {{ __('Pass dates and printing') }}
                                </flux:button>
                            </flux:modal.trigger>

                            <flux:text x-show="hasRemoteRows" x-text="datesSummary" x-cloak data-test="appointment-record-remote-pass-dates-summary"></flux:text>

                            <flux:modal name="remote-pass-dates" class="w-full md:w-2xl" data-test="appointment-record-remote-pass-dates-modal">
                                {{-- Only the dates scroll: the heading and the actions stay in view however many passes there are. --}}
                                <div class="flex max-h-[calc(100dvh-8rem)] flex-col gap-6">
                                    <div class="pe-8">
                                        <flux:heading size="lg">{{ __('Remote pass dates') }}</flux:heading>
                                        <flux:text class="mt-2">{{ __('Dates on which :name will rest to receive each pass. They follow the days of each pass type; adjust them if needed.', ['name' => $person->name]) }}</flux:text>
                                    </div>

                                    {{-- The dates reach the form only when saved, so typing them must not wake the draft autosave. --}}
                                    <div class="-mx-6 min-h-0 flex-1 space-y-6 overflow-y-auto border-y border-zinc-800/10 px-6 py-5 dark:border-white/10" x-on:input.stop x-on:change.stop data-test="appointment-record-remote-pass-dates-body">
                                        {{-- Drawn only by Alpine from the form state, so Livewire's morphing must leave it alone. --}}
                                        <div wire:ignore class="divide-y divide-zinc-800/5 dark:divide-white/10">
                                        <template x-for="row in remoteRows" :key="row.index">
                                            <section class="space-y-3 py-5 first-of-type:pt-0 last-of-type:pb-0">
                                                <div class="flex items-start justify-between gap-3">
                                                    <div class="min-w-0">
                                                        <flux:heading x-text="nameOf(row)"></flux:heading>
                                                        <div class="mt-0.5 flex items-center gap-1.5">
                                                            <flux:icon.clock class="size-4 shrink-0 text-zinc-400 dark:text-zinc-500" />
                                                            <flux:text x-text="scheduleOf(row).schedule ?? labels.noDays"></flux:text>
                                                        </div>
                                                    </div>
                                                    <flux:badge size="sm" class="tabular-nums" x-text="countLabel(row)"></flux:badge>
                                                </div>

                                                <div class="grid gap-3 sm:grid-cols-3">
                                                    <template x-for="(date, position) in chosenDates[row.index] ?? []" :key="position">
                                                        <div class="space-y-1.5">
                                                            <flux:text class="text-xs" x-text="positionLabel(position + 1)"></flux:text>
                                                            <x-date-input
                                                                x-model="chosenDates[row.index][position]"
                                                                :min="$appointment->scheduled_on->addDay()->toDateString()"
                                                                x-bind:aria-label="positionLabel(position + 1)"
                                                                data-test="appointment-record-remote-pass-date-input"
                                                            />
                                                        </div>
                                                    </template>
                                                </div>
                                            </section>
                                        </template>
                                        </div>

                                        <flux:callout icon="information-circle" icon:variant="outline" color="blue">
                                            <flux:callout.text>
                                                {{ __('The days and times come from the') }}
                                                @can('manage', [\App\Models\PassType::class, $appointment->team])
                                                    <flux:link :href="Catalog::PassType->url()" wire:navigate data-test="appointment-record-pass-type-settings-link">{{ __('configuration') }}</flux:link>
                                                @else
                                                    {{ __('configuration') }}
                                                @endcan
                                                {{ __('of each pass type.') }}
                                            </flux:callout.text>
                                        </flux:callout>
                                    </div>

                                    <div class="flex flex-wrap items-center gap-2">
                                        <flux:button variant="ghost" icon="arrow-path" icon:variant="outline" x-on:click="suggestAll()" data-test="appointment-record-suggest-pass-dates-button">
                                            {{ __('Suggest dates') }}
                                        </flux:button>

                                        <flux:spacer />

                                        <flux:modal.close>
                                            <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                                        </flux:modal.close>

                                        <flux:button x-on:click="save()" data-test="appointment-record-save-remote-pass-dates-button">
                                            {{ __('Save') }}
                                        </flux:button>

                                        <flux:button variant="primary" icon="printer" icon:variant="outline" x-on:click="saveAndPrint()" data-test="appointment-record-save-and-print-remote-passes-button">
                                            {{ __('Save and print') }}
                                        </flux:button>
                                    </div>
                                </div>
                            </flux:modal>
                        </div>
                    </div>

                    <flux:separator variant="subtle" />

                    {{-- Picking another removal type does not send a request, so the section asks for the removal date's warning again. --}}
                    <div class="space-y-4" x-on:catalog-picker-change="$wire.form.infiltrationRemoveOn && $wire.$refresh()" data-test="appointment-record-infiltration-section">
                        <flux:heading size="lg">{{ __('Infiltration') }}</flux:heading>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <flux:input wire:model.blur="form.infiltrationSite" :label="__('Site or reason')" :placeholder="__('E.g. Right arm or emotional state')" data-test="appointment-record-infiltration-site-input" />
                            <x-date-input wire:model.live="form.infiltrationRemoveOn" :min="$appointment->scheduled_on->addDay()->toDateString()" :label="__('Remove on')" data-test="appointment-record-infiltration-remove-on-input" />
                        </div>

                        {{-- The side columns squeeze this section, so the columns follow its own width: the three removal places need about half of 42rem. --}}
                        <div class="@container">
                            <div class="grid items-start gap-4 @2xl:grid-cols-2">
                                {{-- The removal place is optional, so clicking the chosen place again clears it. Captured before Flux handles the click, the place is still the chosen one. --}}
                                <flux:radio.group
                                    wire:model.live="form.infiltrationRemovalPlace"
                                    variant="segmented"
                                    :label="__('Where it is removed')"
                                    x-on:click.capture="if ($event.target.closest('ui-radio')?.checked) { $event.stopPropagation(); $wire.set('form.infiltrationRemovalPlace', '') }"
                                    data-test="appointment-record-removal-place-radio"
                                >
                                    @foreach ($this->removalPlaces as $placeOption)
                                        <flux:radio :value="$placeOption['value']" :label="$placeOption['label']" />
                                    @endforeach
                                </flux:radio.group>

                                @if (InfiltrationRemovalPlace::tryFrom($form->infiltrationRemovalPlace)?->schedulesRemoval())
                                    <flux:field>
                                        <flux:label>{{ __('Schedule the removal as') }}</flux:label>
                                        <x-catalog-picker
                                            model="form.removalAppointmentTypeId"
                                            :options="$this->appointmentTypes"
                                            :selected="$form->removalAppointmentTypeId"
                                            :placeholder="__('Select a type...')"
                                            error-key="new_removalAppointmentTypeId"
                                            create="(name) => $wire.createAppointmentType(name, 'removalAppointmentTypeId')"
                                            cache-key="appointment_types"
                                            test-id="appointment-record-removal-type"
                                        />
                                        <flux:error name="form.removalAppointmentTypeId" />
                                    </flux:field>
                                @endif
                            </div>
                        </div>

                        @if ($warning = $this->removalDayWarning)
                            <flux:callout variant="warning" icon="exclamation-triangle" icon:variant="outline" :heading="$warning" data-test="appointment-record-removal-day-not-offered" />
                        @endif
                    </div>

                    <flux:separator variant="subtle" />

                    {{-- Picking another return type does not send a request, so the section asks for the return date's occupancy again. --}}
                    <div class="space-y-4" x-on:catalog-picker-change="$wire.$refresh()" data-test="appointment-record-return-section">
                        <flux:heading size="lg">{{ __('Return') }}</flux:heading>

                        <div class="grid items-start gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <x-date-input wire:model.live="form.returnOn" :min="$appointment->scheduled_on->addDay()->toDateString()" :label="__('Return date')" data-test="appointment-record-return-on-input" />

                                <div class="flex flex-wrap gap-1">
                                    @foreach (AppointmentRecord::RETURN_INTERVALS_DAYS as $days)
                                        <flux:button size="xs" wire:click="setReturnInDays({{ $days }})" x-on:click="$dispatch('return-date-picked')" data-test="appointment-record-return-in-{{ $days }}-days-button">
                                            {{ __('+:days days', ['days' => $days]) }}
                                        </flux:button>
                                    @endforeach
                                </div>
                            </div>

                            @if ($form->returnOn !== '')
                                <div class="space-y-3">
                                    <flux:field>
                                        <flux:label>{{ __('Schedule the return as') }}</flux:label>
                                        <x-catalog-picker
                                            model="form.returnAppointmentTypeId"
                                            :options="$this->appointmentTypes"
                                            :selected="$form->returnAppointmentTypeId"
                                            :placeholder="__('Select a type...')"
                                            error-key="new_returnAppointmentTypeId"
                                            create="(name) => $wire.createAppointmentType(name, 'returnAppointmentTypeId')"
                                            cache-key="appointment_types"
                                            test-id="appointment-record-return-type"
                                        />
                                        <flux:error name="form.returnAppointmentTypeId" />
                                    </flux:field>

                                    @if ($occupancy = $this->returnDayOccupancy)
                                        @if ($occupancy['warning'])
                                            <flux:callout variant="warning" icon="exclamation-triangle" icon:variant="outline" :heading="$occupancy['warning']" data-test="appointment-record-return-day-not-offered" />
                                        @else
                                            <x-pages::appointments.day-occupancy :occupancy="$occupancy" test-prefix="appointment-record-return-day" />
                                        @endif
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>

                    <flux:separator variant="subtle" />

                    <div class="space-y-4">
                        <flux:heading size="lg">{{ __('Observations') }}</flux:heading>
                        <flux:textarea wire:model="form.observations" rows="auto" :label="__('Appointment notes')" data-test="appointment-record-observations-input" />
                    </div>

                    <flux:separator variant="subtle" />

                    <div class="space-y-4">
                        <flux:heading size="lg">{{ __('Mentor') }}</flux:heading>

                        <flux:field>
                            <flux:label>{{ __('Appointment mentor') }}</flux:label>
                            <x-catalog-picker :catalog="Catalog::Mentor" model="form.mentorId" :options="$this->mentors" :selected="$form->mentorId" test-id="appointment-record-mentor" />
                            <flux:error name="form.mentorId" />
                        </flux:field>
                    </div>

                    <div class="sticky bottom-0 z-10 flex flex-col-reverse gap-2 border-t border-zinc-200 bg-white py-3 sm:flex-row sm:items-center sm:justify-end dark:border-white/10 dark:bg-zinc-800">
                        <flux:text x-show="status" x-text="status" x-cloak class="text-xs sm:me-auto" data-test="appointment-record-draft-status"></flux:text>

                        @if ($this->isInProgress)
                            <flux:button variant="ghost" icon="arrow-uturn-left" icon:variant="outline" x-on:click="cancel()" wire:click="returnToQueue" data-test="appointment-record-return-to-queue-button">
                                {{ __('Return to queue') }}
                            </flux:button>
                        @endif

                        <flux:button type="submit" data-test="appointment-record-save-button">
                            {{ __('Save record') }}
                        </flux:button>

                        @if ($this->isInProgress)
                            <flux:button variant="primary" icon="check-circle" icon:variant="outline" x-on:click="cancel()" wire:click="complete" data-test="appointment-record-complete-button">
                                {{ __('Complete appointment') }}
                            </flux:button>
                        @endif
                    </div>
                </form>
            @else
                @php($record = $this->record)

                <div class="space-y-6" data-test="appointment-record-readonly">
                    @if ($this->isLockedByAge)
                        <flux:callout icon="lock-closed" variant="secondary" icon:variant="outline" data-test="appointment-record-locked-notice">
                            <flux:callout.text>
                                {{ __('Records of appointments older than :days days can only be changed by administrators.', ['days' => Appointment::RECORD_EDITING_WINDOW_DAYS]) }}
                            </flux:callout.text>
                        </flux:callout>
                    @endif

                    @if ($record === null)
                        <flux:text>{{ __('No record has been filled for this appointment yet.') }}</flux:text>
                    @else
                        <flux:card>
                            <x-appointments.record-details :record="$record" />
                        </flux:card>
                    @endif
                </div>
            @endif
        </div>
    </div>
</section>
