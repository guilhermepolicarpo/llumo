<?php

use App\Actions\Appointments\PerformAppointmentAction;
use App\Actions\Appointments\SaveAppointmentRecord;
use App\Actions\Appointments\SaveAppointmentRecordDraft;
use App\Concerns\InteractsWithAppointmentRecordForm;
use App\Enums\AppointmentAction;
use App\Enums\AppointmentStatus;
use App\Enums\Catalog;
use App\Enums\InfiltrationRemovalPlace;
use App\Models\Appointment;
use App\Models\AppointmentRecord;
use App\Models\User;
use App\Rules\AppointmentRecordRules;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    use InteractsWithAppointmentRecordForm;

    /**
     * How many previous records the history block lists.
     */
    public const int HISTORY_LIMIT = 5;

    public Appointment $appointment;

    public function mount(Appointment $appointment, PerformAppointmentAction $performAppointmentAction): void
    {
        Gate::authorize('view', [AppointmentRecord::class, $appointment]);

        if (Gate::allows('perform', $appointment) && $performAppointmentAction->handle($appointment, AppointmentAction::Start, Auth::user())) {
            $appointment->refresh();
        }

        $this->appointment = $appointment;

        $this->fillRecordForm($this->record);

        if (Gate::allows('update', [AppointmentRecord::class, $appointment])) {
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
     * Save the record and finish the appointment.
     */
    public function complete(SaveAppointmentRecord $saveAppointmentRecord, PerformAppointmentAction $performAppointmentAction): void
    {
        Gate::authorize('perform', $this->appointment);

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
        Gate::authorize('perform', $this->appointment);
        Gate::authorize('update', [AppointmentRecord::class, $this->appointment]);

        $saveAppointmentRecordDraft->handle($this->appointment, Auth::user(), $this->only($this->draftProperties()));

        if (! $performAppointmentAction->handle($this->appointment, AppointmentAction::ReturnToQueue, Auth::user())) {
            Flux::toast(variant: 'danger', text: __('This action is no longer available for the appointment.'));

            return;
        }

        Flux::toast(variant: 'success', text: __('Appointment moved to :status.', ['status' => AppointmentStatus::Waiting->label()]));

        $this->redirectRoute('appointments.index', navigate: true);
    }

    #[Computed]
    public function canUpdate(): bool
    {
        return Gate::allows('update', [AppointmentRecord::class, $this->appointment]);
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
            ->with(['mentor', 'fluidicRemedies', 'guidances', 'passPrescriptions.passType', 'infiltrationRemovalAppointment.appointmentType', 'returnAppointment.appointmentType'])
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
        return $this->appointment->assistedPerson->appointmentRecords()
            ->where('appointments.status', AppointmentStatus::Completed)
            ->where('appointments.id', '!=', $this->appointment->id)
            ->with(['appointment.appointmentType', 'mentor', 'fluidicRemedies', 'guidances'])
            ->orderByDesc('appointments.scheduled_on')
            ->orderByDesc('appointments.id')
            ->limit(self::HISTORY_LIMIT)
            ->get();
    }

    private function persistRecord(SaveAppointmentRecord $saveAppointmentRecord): void
    {
        Gate::authorize('update', [AppointmentRecord::class, $this->appointment]);

        $validated = $this->validate(AppointmentRecordRules::all($this->appointment));

        $saveAppointmentRecord->handle($this->appointment, $this->recordAttributes($validated));

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
        :back-label="__('Back to appointments')"
    />

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
                    <flux:avatar :name="$person->name" class="shrink-0" />

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
                                <flux:icon :name="$icon" variant="micro" class="mt-0.5 shrink-0 text-zinc-400 dark:text-zinc-500" />
                                <flux:text class="min-w-0 break-words">{{ $detail }}</flux:text>
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
                        <flux:icon name="chevron-right" variant="mini" class="size-4 transition-transform duration-200" x-bind:class="open && 'rotate-90'" />
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
                    </div>
                </div>
            @endif
        </aside>

        <div class="min-w-0 space-y-6">
            @if ($this->otherAttendant)
                <flux:callout variant="warning" icon="exclamation-triangle" :heading="__(':name is already attending this appointment.', ['name' => $this->otherAttendant->name])" data-test="appointment-record-other-attendant" />
            @endif

            @if ($restoredDraft)
                <flux:callout variant="secondary" icon="document-text" inline data-test="appointment-record-restored-draft">
                    <flux:callout.heading>
                        {{ $restoredDraft['name']
                            ? __('We restored an unsaved draft from :time (:name).', $restoredDraft)
                            : __('We restored an unsaved draft from :time.', $restoredDraft) }}
                    </flux:callout.heading>

                    <x-slot name="actions">
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
                    x-on:submit="cancel(); status = ''"
                    x-on:visibilitychange.document="document.hidden && flush()"
                    x-on:livewire:navigating.document="flush()"
                    data-test="appointment-record-form"
                >
                    <div class="space-y-4">
                        <flux:heading size="lg">{{ __('Mentor') }}</flux:heading>

                        <flux:field>
                            <flux:label>{{ __('Appointment mentor') }}</flux:label>
                            <x-catalog-picker :catalog="Catalog::Mentor" model="mentorId" :options="$this->mentors" :selected="$mentorId" test-id="appointment-record-mentor" />
                            <flux:error name="mentorId" />
                        </flux:field>
                    </div>

                    <flux:separator variant="subtle" />

                    <div class="space-y-4">
                        <flux:heading size="lg">{{ __('Fluidic remedy') }}</flux:heading>

                        <flux:field>
                            <flux:label>{{ __('Medications') }}</flux:label>
                            <x-catalog-picker :catalog="Catalog::FluidicRemedy" model="fluidicRemedyIds" :options="$this->fluidicRemedies" :selected="$fluidicRemedyIds" multiple test-id="appointment-record-fluidic-remedies" />
                            <flux:error name="fluidicRemedyIds" />
                            <flux:error name="fluidicRemedyIds.*" />
                        </flux:field>

                        <flux:input wire:model="fluidInstructions" :label="__('How to take')" :placeholder="__('3 times a day, every 3 hours')" data-test="appointment-record-fluid-instructions-input" />
                    </div>

                    <flux:separator variant="subtle" />

                    <div class="space-y-4">
                        <flux:heading size="lg">{{ __('Guidances') }}</flux:heading>

                        <flux:field>
                            <flux:label>{{ __('Recommended guidances') }}</flux:label>
                            <x-catalog-picker
                                :catalog="Catalog::Guidance"
                                model="guidanceIds"
                                :options="$this->guidances"
                                :selected="$guidanceIds"
                                multiple
                                detail-model="guidanceDetails"
                                :detail-placeholder="__('Detail (optional)')"
                                test-id="appointment-record-guidances"
                            />
                        </flux:field>
                        <flux:error name="guidanceIds.*" />
                        <flux:error name="guidanceDetails.*" />
                    </div>

                    <flux:separator variant="subtle" />

                    <div class="space-y-4">
                        <flux:heading size="lg">{{ __('Passes') }}</flux:heading>

                        @foreach ($passPrescriptions as $index => $prescription)
                            <div class="space-y-2" wire:key="pass-prescription-{{ $index }}" data-test="appointment-record-pass-prescription">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                                    <flux:field class="min-w-0 flex-1">
                                        <flux:label>{{ __('Pass type') }}</flux:label>
                                        <x-catalog-picker :catalog="Catalog::PassType" model="passPrescriptions.{{ $index }}.pass_type_id" :options="$this->passTypes" :selected="$prescription['pass_type_id']" :index="$index" test-id="appointment-record-pass-type-{{ $index }}" />
                                    </flux:field>

                                    <div class="w-24">
                                        <flux:input type="number" min="1" max="99" wire:model="passPrescriptions.{{ $index }}.quantity" :label="__('Quantity')" data-test="appointment-record-pass-quantity-input" />
                                    </div>

                                    <flux:radio.group wire:model="passPrescriptions.{{ $index }}.mode" variant="segmented" :label="__('Mode')">
                                        @foreach ($this->modes as $modeOption)
                                            <flux:radio :value="$modeOption['value']" :label="$modeOption['label']" />
                                        @endforeach
                                    </flux:radio.group>

                                    <flux:button variant="ghost" size="sm" icon="x-mark" class="self-end sm:mb-1.5" wire:click="removePassPrescription({{ $index }})" :aria-label="__('Remove pass')" data-test="appointment-record-remove-pass-button" />
                                </div>

                                <flux:error name="passPrescriptions.{{ $index }}.pass_type_id" />
                                <flux:error name="passPrescriptions.{{ $index }}.quantity" />
                                <flux:error name="passPrescriptions.{{ $index }}.mode" />
                            </div>
                        @endforeach

                        <flux:button size="sm" icon="plus" wire:click="addPassPrescription" data-test="appointment-record-add-pass-button">
                            {{ __('Add pass') }}
                        </flux:button>
                    </div>

                    <flux:separator variant="subtle" />

                    <div class="space-y-4">
                        <flux:heading size="lg">{{ __('Infiltration') }}</flux:heading>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <flux:input wire:model.blur="infiltrationSite" :label="__('Site')" :placeholder="__('Right arm')" data-test="appointment-record-infiltration-site-input" />
                            <flux:input type="date" wire:model="infiltrationRemoveOn" :min="$appointment->scheduled_on->addDay()->toDateString()" :label="__('Remove on')" data-test="appointment-record-infiltration-remove-on-input" />
                        </div>

                        <flux:radio.group wire:model.live="infiltrationRemovalPlace" variant="segmented" :label="__('Where it is removed')" data-test="appointment-record-removal-place-radio">
                            @foreach ($this->removalPlaces as $placeOption)
                                <flux:radio :value="$placeOption['value']" :label="$placeOption['label']" />
                            @endforeach
                        </flux:radio.group>

                        @if (InfiltrationRemovalPlace::tryFrom($infiltrationRemovalPlace)?->schedulesRemoval())
                            <flux:field>
                                <flux:label>{{ __('Schedule the removal as') }}</flux:label>
                                <x-catalog-picker
                                    model="removalAppointmentTypeId"
                                    :options="$this->appointmentTypes"
                                    :selected="$removalAppointmentTypeId"
                                    :placeholder="__('Select a type...')"
                                    error-key="new_removalAppointmentTypeId"
                                    create="(name) => $wire.createAppointmentType(name, 'removalAppointmentTypeId')"
                                    cache-key="appointment_types"
                                    test-id="appointment-record-removal-type"
                                />
                                <flux:error name="removalAppointmentTypeId" />
                            </flux:field>
                        @endif
                    </div>

                    <flux:separator variant="subtle" />

                    <div class="space-y-4">
                        <flux:heading size="lg">{{ __('Return') }}</flux:heading>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <flux:input type="date" wire:model="returnOn" :min="$appointment->scheduled_on->addDay()->toDateString()" :label="__('Return date')" data-test="appointment-record-return-on-input" />

                            @if ($schedulesReturn)
                                <flux:field>
                                    <flux:label>{{ __('Schedule the return as') }}</flux:label>
                                    <x-catalog-picker
                                        model="returnAppointmentTypeId"
                                        :options="$this->appointmentTypes"
                                        :selected="$returnAppointmentTypeId"
                                        :placeholder="__('Select a type...')"
                                        error-key="new_returnAppointmentTypeId"
                                        create="(name) => $wire.createAppointmentType(name, 'returnAppointmentTypeId')"
                                        cache-key="appointment_types"
                                        test-id="appointment-record-return-type"
                                    />
                                    <flux:error name="returnAppointmentTypeId" />
                                </flux:field>
                            @endif
                        </div>

                        <flux:checkbox wire:model.live="schedulesReturn" :label="__('Schedule the return')" data-test="appointment-record-schedules-return-checkbox" />
                    </div>

                    <flux:separator variant="subtle" />

                    <div class="space-y-4">
                        <flux:heading size="lg">{{ __('Observations') }}</flux:heading>
                        <flux:textarea wire:model="observations" rows="4" :label="__('Appointment notes')" data-test="appointment-record-observations-input" />
                    </div>

                    <div class="sticky bottom-0 z-10 flex flex-col-reverse gap-2 border-t border-zinc-200 bg-white py-3 sm:flex-row sm:items-center sm:justify-end dark:border-white/10 dark:bg-zinc-800">
                        <flux:text x-show="status" x-text="status" x-cloak class="text-xs sm:me-auto" data-test="appointment-record-draft-status"></flux:text>

                        @if ($this->isInProgress)
                            <flux:button variant="ghost" icon="arrow-uturn-left" x-on:click="cancel()" wire:click="returnToQueue" data-test="appointment-record-return-to-queue-button">
                                {{ __('Return to queue') }}
                            </flux:button>
                        @endif

                        <flux:button type="submit" data-test="appointment-record-save-button">
                            {{ __('Save record') }}
                        </flux:button>

                        @if ($this->isInProgress)
                            <flux:button variant="primary" icon="check-circle" x-on:click="cancel()" wire:click="complete" data-test="appointment-record-complete-button">
                                {{ __('Complete appointment') }}
                            </flux:button>
                        @endif
                    </div>
                </form>
            @else
                @php($record = $this->record)

                <div class="space-y-6" data-test="appointment-record-readonly">
                    @if ($record === null)
                        <flux:text>{{ __('No record has been filled for this appointment yet.') }}</flux:text>
                    @else
                        <flux:card>
                            <dl class="grid gap-x-6 gap-y-4 text-sm sm:grid-cols-[12rem_1fr]">
                                @foreach (array_filter([
                                    __('Mentor') => $record->mentor?->name,
                                    __('Fluidic remedies') => $record->fluidicRemedies->pluck('name')->implode(', '),
                                    __('How to take the fluidic remedy') => $record->fluid_instructions,
                                    __('Guidances') => $record->guidance_summary,
                                    __('Passes') => $record->passPrescriptions->map(fn ($prescription) => "{$prescription->quantity}× {$prescription->passType->name} ({$prescription->mode->label()})")->implode(', '),
                                    __('Infiltration') => collect([
                                        $record->infiltration_site,
                                        $record->infiltration_remove_on ? __('remove on :date', ['date' => $record->infiltration_remove_on->format('d/m/Y')]) : null,
                                        $record->infiltration_removal_place?->label(),
                                    ])->filter()->implode(' · '),
                                    __('Return date') => $record->return_on?->format('d/m/Y'),
                                    __('Observations') => $record->observations,
                                ], fn ($value) => filled($value)) as $label => $value)
                                    <dt class="text-zinc-500 dark:text-zinc-400">{{ $label }}</dt>
                                    <dd class="whitespace-pre-line text-zinc-900 dark:text-white">{{ $value }}</dd>
                                @endforeach
                            </dl>
                        </flux:card>
                    @endif
                </div>
            @endif
        </div>
    </div>
</section>
