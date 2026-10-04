<?php

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\AppointmentRecord;
use App\Models\AppointmentType;
use App\Models\AssistedPerson;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    /**
     * How many previous appointments the history shows at first, and how many more each "load more" adds.
     */
    public const int PER_PAGE = 10;

    public AssistedPerson $assistedPerson;

    public string $appointmentTypeId = '';

    public string $status = '';

    /**
     * How many previous appointments are currently shown.
     */
    public int $limit = self::PER_PAGE;

    public function mount(AssistedPerson $assistedPerson): void
    {
        Gate::authorize('view', $assistedPerson);

        $this->assistedPerson = $assistedPerson;
    }

    /**
     * Start again from the first previous appointments whenever a filter changes.
     */
    public function updated(): void
    {
        $this->limit = self::PER_PAGE;
    }

    /**
     * Show the next batch of previous appointments.
     */
    public function loadMore(): void
    {
        $this->limit += self::PER_PAGE;
    }

    /**
     * Get the appointments still ahead, the nearest first.
     *
     * @return Collection<int, Appointment>
     */
    #[Computed]
    public function upcomingAppointments(): Collection
    {
        return $this->filteredAppointments()
            ->upcoming()
            ->with(['appointmentType', 'returnOfRecord.appointment', 'infiltrationRemovalOfRecord.appointment'])
            ->orderBy('scheduled_on')
            ->orderBy('id')
            ->get();
    }

    /**
     * Get the appointments no longer ahead, latest first, up to the current limit.
     *
     * @return Collection<int, Appointment>
     */
    #[Computed]
    public function previousAppointments(): Collection
    {
        return $this->filteredAppointments()
            ->past()
            ->with(['appointmentType', 'record.mentor', 'record.fluidicRemedies', 'record.guidances', 'record.passPrescriptions.passType'])
            ->orderByDesc('scheduled_on')
            ->orderByDesc('id')
            ->limit($this->limit)
            ->get();
    }

    /**
     * Count every previous appointment matching the filters, including those not loaded yet.
     */
    #[Computed]
    public function previousCount(): int
    {
        return $this->filteredAppointments()->past()->count();
    }

    /**
     * Count the completed and missed appointments, and find when the first and last visits were.
     *
     * @return array{completed: int, no_shows: int, first_visit_on: CarbonInterface|null, last_visit_on: CarbonInterface|null}
     */
    #[Computed]
    public function stats(): array
    {
        $totals = $this->assistedPerson->appointments()
            ->whereIn('status', [AppointmentStatus::Completed, AppointmentStatus::NoShow])
            ->toBase()
            ->selectRaw('status, count(*) as total, min(scheduled_on) as first_on, max(scheduled_on) as last_on')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $completed = $totals->get(AppointmentStatus::Completed->value);

        return [
            'completed' => (int) ($completed->total ?? 0),
            'no_shows' => (int) ($totals->get(AppointmentStatus::NoShow->value)->total ?? 0),
            'first_visit_on' => $completed ? Date::parse($completed->first_on) : null,
            'last_visit_on' => $completed ? Date::parse($completed->last_on) : null,
        ];
    }

    /**
     * Get the record of the assisted person's latest completed appointment, whose follow-ups are the ones still due.
     */
    #[Computed]
    public function latestRecord(): ?AppointmentRecord
    {
        return $this->assistedPerson->completedAppointmentRecords()
            ->with(['appointment', 'mentor'])
            ->first();
    }

    /**
     * List the follow-ups the latest record asked for that nobody has taken care of: an infiltration removal or a return
     * still to be scheduled, or a return whose date went by without the assisted person coming back.
     *
     * Any appointment already booked counts as the return, since it may have been scheduled without the record's link.
     *
     * @return list<array{key: string, message: string, detail: string, action: string, url: string}>
     */
    #[Computed]
    public function pendingFollowUps(): array
    {
        $record = $this->latestRecord;

        if ($record === null) {
            return [];
        }

        $bookedIds = $this->assistedPerson->appointments()->upcoming()->pluck('id');
        $detail = $record->mentor
            ? __('Indicated by :mentor in the record of :date.', ['mentor' => $record->mentor->name, 'date' => $record->appointment->scheduled_on->format('d/m/Y')])
            : __('Indicated in the record of :date.', ['date' => $record->appointment->scheduled_on->format('d/m/Y')]);
        $followUps = [];

        if ($record->infiltration_removal_place?->schedulesRemoval()
            && $record->infiltration_remove_on?->gte(today())
            && ! $bookedIds->contains($record->infiltration_removal_appointment_id)) {
            $followUps[] = [
                'key' => 'infiltration',
                'message' => __('The infiltration removal on :date has not been scheduled yet.', ['date' => $record->infiltration_remove_on->format('d/m/Y')]),
                'detail' => $detail,
                'action' => __('Schedule removal'),
                'url' => $this->newAppointmentUrl($record->infiltration_remove_on),
            ];
        }

        $cameBack = $this->stats['last_visit_on']?->gt($record->appointment->scheduled_on) ?? false;
        $hasBookedReturn = $bookedIds->contains(fn (int $id): bool => $id !== $record->infiltration_removal_appointment_id);

        if ($record->return_on && ! $cameBack && ! $hasBookedReturn) {
            $isAhead = $record->return_on->gte(today());

            $followUps[] = [
                'key' => 'return',
                'message' => $isAhead
                    ? __('The return indicated for :date has not been scheduled yet.', ['date' => $record->return_on->format('d/m/Y')])
                    : __('The return indicated for :date went by without the assisted person coming back.', ['date' => $record->return_on->format('d/m/Y')]),
                'detail' => $detail,
                'action' => __('Schedule return'),
                'url' => $this->newAppointmentUrl($isAhead ? $record->return_on : null),
            ];
        }

        return $followUps;
    }

    /**
     * Get the team's appointment types available to filter on.
     *
     * @return Collection<int, AppointmentType>
     */
    #[Computed]
    public function appointmentTypes(): Collection
    {
        return AppointmentType::query()->where('team_id', $this->assistedPerson->team_id)->orderBy('name')->get(['id', 'name']);
    }

    /**
     * Get the link to schedule a new appointment for the assisted person, optionally on the given date.
     */
    public function newAppointmentUrl(?CarbonInterface $date = null): string
    {
        return route('appointments.create', array_filter([
            'assisted_person' => $this->assistedPerson->id,
            'date' => $date?->toDateString(),
        ]));
    }

    /**
     * Start a query for the assisted person's appointments narrowed by the filters.
     *
     * @return Builder<Appointment>
     */
    private function filteredAppointments(): Builder
    {
        return $this->assistedPerson->appointments()->getQuery()
            ->when($this->appointmentTypeId !== '', fn (Builder $query) => $query->where('appointment_type_id', $this->appointmentTypeId))
            ->when(AppointmentStatus::tryFrom($this->status), fn (Builder $query, AppointmentStatus $status) => $query->where('status', $status));
    }

    public function render()
    {
        return $this->view()->title(__('History of :name', ['name' => $this->assistedPerson->name]));
    }
}; ?>

@php
    $chipClasses = 'inline-flex items-center rounded-md bg-zinc-100 px-2.5 py-0.5 text-zinc-800 dark:bg-white/10 dark:text-zinc-200';
@endphp

<section class="w-full max-w-5xl">
    <x-page-header :heading="$assistedPerson->name" :back-href="route('assisted-people.index')" :back-label="__('Back to assisted people')" :separator="false" stack-until="lg">
        <x-slot:leading class="ms-1">
            <flux:avatar circle size="lg" :name="$assistedPerson->name" />
        </x-slot:leading>

        <x-slot:subheading>
            <span class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-sm" data-test="assisted-person-history-details">
                @foreach (array_filter([
                    'cake' => $assistedPerson->formatted_age ? $assistedPerson->formatted_age.' · '.$assistedPerson->birth_date->format('d/m/Y') : null,
                    'phone' => $assistedPerson->formatted_phone,
                    'envelope' => $assistedPerson->email,
                    'map-pin' => $assistedPerson->formatted_address,
                ]) as $icon => $detail)
                    <span class="flex items-center gap-1.5">
                        <flux:icon :name="$icon" class="size-4 shrink-0 text-zinc-400 dark:text-zinc-500" />
                        {{ $detail }}
                    </span>
                @endforeach
            </span>

            <div class="mt-3 flex flex-wrap gap-2" data-test="assisted-person-history-stats">
                @foreach (array_filter([
                    trans_choice(':count appointment|:count appointments', $this->stats['completed']),
                    trans_choice(':count no-show|:count no-shows', $this->stats['no_shows']),
                    ($firstVisitOn = $this->stats['first_visit_on']) ? __('Since :date', ['date' => $firstVisitOn->format('d/m/Y')]) : null,
                    ($lastVisitOn = $this->stats['last_visit_on']) ? __('Last visit :when', ['when' => $lastVisitOn->isToday() ? __('today') : $lastVisitOn->diffForHumans()]) : null,
                ]) as $stat)
                    <flux:badge rounded size="sm" color="zinc" class="text-[13px]!">{{ $stat }}</flux:badge>
                @endforeach
            </div>
        </x-slot:subheading>

        <div class="flex shrink-0 flex-wrap gap-2">
            <flux:button icon="pencil-square" icon:variant="outline" :href="route('assisted-people.edit', ['assistedPerson' => $assistedPerson])" wire:navigate data-test="assisted-person-history-edit-button">
                {{ __('Edit registration') }}
            </flux:button>

            <flux:button variant="primary" icon="plus" :href="$this->newAppointmentUrl()" wire:navigate data-test="assisted-person-history-new-appointment-button">
                {{ __('New appointment') }}
            </flux:button>
        </div>
    </x-page-header>

    @foreach ($this->pendingFollowUps as $followUp)
        <flux:callout variant="warning" icon="exclamation-triangle" icon:variant="outline" inline class="mb-4" wire:key="follow-up-{{ $followUp['key'] }}" data-test="assisted-person-history-follow-up">
            <flux:callout.heading>{{ $followUp['message'] }}</flux:callout.heading>
            <flux:callout.text>{{ $followUp['detail'] }}</flux:callout.text>

            <x-slot name="actions" class="@md:self-center">
                <flux:button size="sm" :href="$followUp['url']" wire:navigate data-test="assisted-person-history-follow-up-button">{{ $followUp['action'] }}</flux:button>
            </x-slot>
        </flux:callout>
    @endforeach

    <section class="mt-8" data-test="assisted-person-history">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-baseline gap-2.5">
                <flux:heading size="lg" level="2">{{ __('Attendances') }}</flux:heading>
                <flux:text size="sm">{{ __(':count in total', ['count' => $this->upcomingAppointments->count() + $this->previousCount]) }}</flux:text>
            </div>

            <div class="grid w-full grid-cols-2 gap-2 sm:flex sm:w-auto">
                <flux:select wire:model.live="appointmentTypeId" size="sm" class="sm:w-52" :aria-label="__('Appointment type')" data-test="assisted-person-history-type-filter">
                    <flux:select.option value="">{{ __('All types') }}</flux:select.option>
                    @foreach ($this->appointmentTypes as $appointmentType)
                        <flux:select.option :value="(string) $appointmentType->id">{{ $appointmentType->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model.live="status" size="sm" class="sm:w-44" :aria-label="__('Status')" data-test="assisted-person-history-status-filter">
                    <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
                    @foreach (AppointmentStatus::options() as $option)
                        <flux:select.option :value="$option['value']">{{ $option['label'] }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        </div>

        @if ($this->upcomingAppointments->isEmpty() && $this->previousAppointments->isEmpty())
            <flux:text class="py-8 text-center text-zinc-500 dark:text-zinc-400" data-test="assisted-person-history-empty">
                @if ($appointmentTypeId !== '' || $status !== '')
                    {{ __('No appointments match your filters.') }}
                @else
                    {{ __('No appointments have been recorded for this assisted person yet.') }}
                @endif
            </flux:text>
        @endif

        @if ($this->upcomingAppointments->isNotEmpty())
            <div class="flex items-center gap-2.5 pb-3">
                <h3 class="text-xs font-semibold tracking-wider text-zinc-500 uppercase dark:text-zinc-400">{{ __('Upcoming') }}</h3>
                <span class="h-px grow bg-zinc-200 dark:bg-white/10"></span>
            </div>

            <ol class="mb-4">
                @foreach ($this->upcomingAppointments as $appointment)
                    @php
                        $origin = match (true) {
                            $appointment->returnOfRecord !== null => __('return from the record of :date', ['date' => $appointment->returnOfRecord->appointment->scheduled_on->format('d/m')]),
                            $appointment->infiltrationRemovalOfRecord !== null => __('infiltration removal from the record of :date', ['date' => $appointment->infiltrationRemovalOfRecord->appointment->scheduled_on->format('d/m')]),
                            default => null,
                        };
                    @endphp
                    <li class="flex gap-4" wire:key="upcoming-{{ $appointment->id }}" data-test="assisted-person-history-upcoming-entry">
                        <div class="flex w-14 shrink-0 flex-col items-center pt-1">
                            <span class="text-[11px]/[14px] font-semibold tracking-wide text-zinc-500 uppercase dark:text-zinc-400">{{ $appointment->scheduled_on->translatedFormat('M') }}</span>
                            <span class="text-[22px]/[26px] font-semibold text-zinc-900 dark:text-white">{{ $appointment->scheduled_on->format('d') }}</span>
                            <span class="text-[11px]/[14px] font-semibold tracking-wide text-zinc-500 uppercase dark:text-zinc-400">{{ $appointment->scheduled_on->isToday() ? __('today') : $appointment->scheduled_on->translatedFormat('D') }}</span>
                        </div>

                        <x-assisted-people.timeline-rail :status="$appointment->status" offset="upcoming" :first="$loop->first" :last="$loop->last" />

                        <div class="mb-3 flex min-w-0 grow flex-wrap items-center gap-x-3 gap-y-2 rounded-xl border border-dashed border-zinc-300 px-4 py-3 sm:px-5 dark:border-white/20">
                            <div class="min-w-0 grow">
                                <div class="text-[15px] font-medium text-zinc-900 dark:text-white">{{ $appointment->appointmentType->name }}</div>
                                <div class="mt-0.5 flex flex-wrap items-center gap-x-1.5 text-[13px] text-zinc-600 dark:text-zinc-400">
                                    <flux:icon :icon="$appointment->mode->icon()" class="size-3.5" />
                                    {{ $appointment->mode->label() }}
                                    @if ($origin)
                                        <span aria-hidden="true">·</span>
                                        {{ $origin }}
                                    @endif
                                </div>
                                @if ($appointment->notes)
                                    <div class="mt-2 flex items-start gap-1.5 text-sm text-zinc-600 dark:text-zinc-400">
                                        <flux:icon.chat-bubble-left-ellipsis class="mt-0.5 size-4 shrink-0 text-zinc-400 dark:text-zinc-500" />
                                        <span class="min-w-0 whitespace-pre-line">{{ $appointment->notes }}</span>
                                    </div>
                                @endif
                            </div>

                            <flux:badge size="sm" :color="$appointment->status->color()">{{ $appointment->status->label() }}</flux:badge>
                        </div>
                    </li>
                @endforeach
            </ol>
        @endif

        @if ($this->previousAppointments->isNotEmpty())
            <div class="flex items-center gap-2.5 pt-2 pb-3">
                <h3 class="text-xs font-semibold tracking-wider text-zinc-500 uppercase dark:text-zinc-400">{{ __('Previous') }}</h3>
                <span class="h-px grow bg-zinc-200 dark:bg-white/10"></span>
            </div>

            @php
                $hasMore = $this->previousCount > $this->previousAppointments->count();
            @endphp

            <div>
                @foreach ($this->previousAppointments->groupBy(fn (Appointment $appointment) => $appointment->scheduled_on->format('Y-m')) as $month => $appointments)
                    <div class="flex gap-4" wire:key="month-{{ $month }}">
                        <span class="w-14 shrink-0"></span>
                        <div aria-hidden="true" class="flex w-3 shrink-0 justify-center">
                            <span @class(['w-px bg-zinc-200 dark:bg-white/10', 'invisible' => $loop->first])></span>
                        </div>
                        <h4 class="pt-1 pb-3 text-sm font-semibold text-zinc-700 dark:text-zinc-300">{{ Str::ucfirst($appointments->first()->scheduled_on->translatedFormat('F \d\e Y')) }}</h4>
                    </div>

                    @php
                        $isLastMonth = $loop->last;
                    @endphp
                    <ol>
                        @foreach ($appointments as $appointment)
                            @php
                                $isMissed = in_array($appointment->status, [AppointmentStatus::NoShow, AppointmentStatus::Canceled], true);
                                $isFirst = $loop->parent->first && $loop->first;
                                $isLast = $isLastMonth && $loop->last && ! $hasMore;
                                $record = $appointment->filledRecord();
                            @endphp
                            <li class="flex gap-4" wire:key="previous-{{ $appointment->id }}" data-test="assisted-person-history-entry">
                                <div @class(['flex w-14 shrink-0 flex-col items-center', 'pt-1.5' => $isMissed, 'pt-4' => ! $isMissed])>
                                    <span @class(['text-[22px]/[26px] font-semibold', 'text-zinc-500 dark:text-zinc-400' => $isMissed, 'text-zinc-900 dark:text-white' => ! $isMissed])>{{ $appointment->scheduled_on->format('d') }}</span>
                                    <span class="text-[11px]/[14px] font-semibold tracking-wide text-zinc-500 uppercase dark:text-zinc-400">{{ $appointment->scheduled_on->translatedFormat('D') }}</span>
                                </div>

                                <x-assisted-people.timeline-rail :status="$appointment->status" :offset="$isMissed ? 'row' : 'card'" :first="$isFirst" :last="$isLast" />

                                @if ($isMissed)
                                    <div class="mb-3 flex min-w-0 grow flex-wrap items-center gap-x-3 gap-y-1 px-4 py-2.5 sm:px-5">
                                        <div class="min-w-0 grow">
                                            <div class="text-[15px] font-medium text-zinc-600 dark:text-zinc-400">{{ $appointment->appointmentType->name }}</div>
                                            <div class="mt-0.5 text-[13px] text-zinc-500 dark:text-zinc-400">{{ $appointment->mode->label() }}</div>
                                        </div>

                                        <flux:badge size="sm" :color="$appointment->status->color()" data-test="assisted-person-history-status">{{ $appointment->status->label() }}</flux:badge>
                                    </div>
                                @else
                                    <div class="mb-3 min-w-0 grow space-y-4 rounded-xl border border-zinc-200 p-4 sm:px-5 dark:border-white/10">
                                        <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                                            <div class="min-w-0 grow">
                                                <div class="text-[15px] font-medium text-zinc-900 dark:text-white">{{ $appointment->appointmentType->name }}</div>
                                                <div class="mt-0.5 flex flex-wrap items-center gap-x-1.5 text-[13px] text-zinc-600 dark:text-zinc-400">
                                                    <flux:icon :icon="$appointment->mode->icon()" class="size-3.5" />
                                                    {{ $appointment->mode->label() }}
                                                    @if ($record?->mentor)
                                                        <span aria-hidden="true">·</span>
                                                        {{ __('with :name', ['name' => $record->mentor->name]) }}
                                                    @endif
                                                </div>
                                            </div>

                                            <div class="flex items-center gap-2">
                                                @if ($record && $appointment->hasPrintableAttendanceSheet())
                                                    <flux:button
                                                        variant="ghost"
                                                        size="sm"
                                                        icon="printer"
                                                        icon:variant="outline"
                                                        :href="route('appointments.attendance-sheet', ['appointment' => $appointment])"
                                                        target="_blank"
                                                        data-test="assisted-person-history-print-record-button"
                                                        >
                                                        {{ __('Print attendance sheet') }}
                                                    </flux:button>
                                                @endif

                                                @if ($appointment->status === AppointmentStatus::Completed && $appointment->usesRecord())
                                                    <flux:button
                                                        variant="ghost"
                                                        size="sm"
                                                        icon="arrow-top-right-on-square"
                                                        icon:variant="outline"
                                                        :href="route('appointments.attend', ['appointment' => $appointment])"
                                                        wire:navigate
                                                        data-test="assisted-person-history-record-button"
                                                        >
                                                        {{ __('Open record') }}
                                                    </flux:button>
                                                @endif

                                                <flux:badge size="sm" :color="$appointment->status->color()" data-test="assisted-person-history-status">{{ $appointment->status->label() }}</flux:badge>
                                            </div>
                                        </div>

                                        @if ($appointment->notes)
                                            <div class="flex items-start gap-1.5 text-sm text-zinc-600 dark:text-zinc-400">
                                                <flux:icon.chat-bubble-left-ellipsis class="mt-0.5 size-4 shrink-0 text-zinc-400 dark:text-zinc-500" />
                                                <span class="min-w-0 whitespace-pre-line">{{ $appointment->notes }}</span>
                                            </div>
                                        @endif

                                        @if ($record)
                                            <dl class="grid gap-x-4 text-sm sm:grid-cols-[7rem_minmax(0,1fr)] sm:gap-y-3 [&>dd]:mb-3 sm:[&>dd]:mb-0 [&>dt]:text-[13px] [&>dt]:text-zinc-500 sm:[&>dt]:pt-0.5 dark:[&>dt]:text-zinc-400" data-test="assisted-person-history-record">
                                                @if ($record->fluidicRemedies->isNotEmpty())
                                                    <dt>{{ __('Fluidic remedies') }}</dt>
                                                    <dd class="flex flex-wrap items-center gap-1.5">
                                                        @foreach ($record->fluidicRemedies as $remedy)
                                                            <span class="{{ $chipClasses }}" wire:key="remedy-{{ $record->id }}-{{ $remedy->id }}">{{ $remedy->name }}</span>
                                                        @endforeach
                                                        @if ($record->fluid_instructions)
                                                            <span class="basis-full text-[13px] text-zinc-600 dark:text-zinc-400">{{ $record->fluid_instructions }}</span>
                                                        @endif
                                                    </dd>
                                                @endif

                                                @if ($record->guidances->isNotEmpty())
                                                    <dt>{{ __('Guidances') }}</dt>
                                                    <dd class="flex flex-wrap items-center gap-1.5">
                                                        @foreach ($record->guidances as $guidance)
                                                            <span class="{{ $chipClasses }}" wire:key="guidance-{{ $record->id }}-{{ $guidance->id }}">
                                                                {{ $guidance->name }}
                                                                @if ($guidance->pivot->detail)
                                                                    <span class="ms-1 text-zinc-500 dark:text-zinc-400">· {{ $guidance->pivot->detail }}</span>
                                                                @endif
                                                            </span>
                                                        @endforeach
                                                    </dd>
                                                @endif

                                                @if ($record->passPrescriptions->isNotEmpty())
                                                    <dt>{{ __('Passes') }}</dt>
                                                    <dd class="flex flex-wrap items-center gap-1.5">
                                                        @foreach ($record->passPrescriptions as $prescription)
                                                            <span class="{{ $chipClasses }}" wire:key="pass-{{ $prescription->id }}">{{ $prescription->label }}</span>
                                                        @endforeach
                                                    </dd>
                                                @endif

                                                @if ($infiltration = $record->infiltration_summary)
                                                    <dt>{{ __('Infiltration') }}</dt>
                                                    <dd class="text-zinc-800 dark:text-zinc-200">{{ $infiltration }}</dd>
                                                @endif

                                                @if ($record->return_on)
                                                    <dt>{{ __('Return') }}</dt>
                                                    <dd class="text-zinc-800 dark:text-zinc-200">{{ $record->return_on->format('d/m/Y') }}</dd>
                                                @endif

                                                @if ($record->observations)
                                                    <dt>{{ __('Observations') }}</dt>
                                                    <dd class="whitespace-pre-line text-zinc-800 dark:text-zinc-200">{{ $record->observations }}</dd>
                                                @endif
                                            </dl>
                                        @endif
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                @endforeach
            </div>

            @if ($hasMore)
                <div class="mt-4 flex justify-center">
                    <flux:button wire:click="loadMore" data-test="assisted-person-history-load-more-button">
                        {{ __('Load more appointments') }}
                    </flux:button>
                </div>
            @endif
        @endif
    </section>
</section>
