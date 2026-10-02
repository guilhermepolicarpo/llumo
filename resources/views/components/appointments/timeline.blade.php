{{-- The appointment's path from scheduling to its conclusion, one step per milestone, ending early when the assisted person missed it or it was canceled. --}}
{{-- Only the arrival is recorded as it happens: the start and finish are when the attendance was entered in the system, so neither is told as a duration. --}}
@props([
    'appointment',
    'waitingFor' => null,
])

@use('App\Enums\AppointmentStatus')

@php
    $status = $appointment->status;

    $steps = [[
        'label' => AppointmentStatus::Scheduled->label(),
        'detail' => __($appointment->creator ? 'by :user on :date at :time' : 'on :date at :time', [
            'user' => $appointment->creator?->name,
            'date' => $appointment->created_at->format('d/m/Y'),
            'time' => $appointment->created_at->format('H:i'),
        ]),
        'state' => 'done',
        'test' => 'appointment-details-scheduling',
    ]];

    if ($appointment->mode->expectsArrival() && ($appointment->received_at || in_array($status, [AppointmentStatus::Scheduled, AppointmentStatus::NoShow], true))) {
        $steps[] = match (true) {
            (bool) $appointment->received_at => [
                'label' => __('Arrived at :time', ['time' => $appointment->received_at->format('H:i')]),
                'detail' => __('Received at the front desk'),
                'state' => 'done',
                'badge' => $waitingFor,
                'test' => 'appointment-details-arrival',
            ],
            $status === AppointmentStatus::NoShow => [
                'label' => $status->label(),
                'state' => 'missed',
                'test' => 'appointment-details-arrival',
            ],
            default => [
                'label' => __('Not arrived yet'),
                'state' => 'upcoming',
                'test' => 'appointment-details-arrival',
            ],
        };
    }

    if ($status === AppointmentStatus::Canceled) {
        $steps[] = ['label' => $status->label(), 'state' => 'canceled'];
    } elseif ($status !== AppointmentStatus::NoShow) {
        $steps[] = [
            'label' => AppointmentStatus::InProgress->label(),
            // Once concluded, who entered it in the system is told by the conclusion and who attended by the record's mentor.
            'detail' => match (true) {
                $status === AppointmentStatus::Completed => __('Attendance took place'),
                (bool) $appointment->attendant => __('Attendant: :name', ['name' => $appointment->attendant->name]),
                default => null,
            },
            'state' => match ($status) {
                AppointmentStatus::InProgress => 'current',
                AppointmentStatus::Completed => 'done',
                default => 'upcoming',
            },
            'test' => 'appointment-details-attending',
        ];

        $isEntered = $status === AppointmentStatus::Completed && $appointment->started_at && $appointment->finished_at;

        $steps[] = [
            'label' => __('Concluded'),
            'detail' => match (true) {
                $isEntered && $appointment->attendant && $appointment->started_at->format('H:i') === $appointment->finished_at->format('H:i') => __('Entered in the system by :user, at :start.', ['user' => $appointment->attendant->name, 'start' => $appointment->started_at->format('H:i')]),
                $isEntered && $appointment->attendant => __('Entered in the system by :user, from :start to :end.', ['user' => $appointment->attendant->name, 'start' => $appointment->started_at->format('H:i'), 'end' => $appointment->finished_at->format('H:i')]),
                $isEntered && $appointment->started_at->format('H:i') === $appointment->finished_at->format('H:i') => __('Entered in the system at :start.', ['start' => $appointment->started_at->format('H:i')]),
                $isEntered => __('Entered in the system from :start to :end.', ['start' => $appointment->started_at->format('H:i'), 'end' => $appointment->finished_at->format('H:i')]),
                $status !== AppointmentStatus::Completed && $appointment->usesRecord() => __('when the appointment record is saved'),
                default => null,
            },
            'state' => $status === AppointmentStatus::Completed ? 'done' : 'upcoming',
            'test' => $isEntered ? 'appointment-details-system-entry' : 'appointment-details-conclusion',
        ];
    }
@endphp

<ol aria-label="{{ __('Timeline') }}" {{ $attributes->class('flex flex-col') }} data-test="appointment-details-timeline">
    @foreach ($steps as $step)
        <li class="flex gap-3" data-test="{{ $step['test'] ?? 'appointment-details-step' }}" data-state="{{ $step['state'] }}">
            <div aria-hidden="true" class="flex w-5 shrink-0 flex-col items-center">
                @switch($step['state'])
                    @case('done')
                        <span class="flex size-5 shrink-0 items-center justify-center rounded-full bg-green-100 text-green-700 dark:bg-green-400/20 dark:text-green-300">
                            <flux:icon.check class="size-3" />
                        </span>
                        @break
                    @case('current')
                        <span class="flex size-5 shrink-0 items-center justify-center rounded-full border-2 border-blue-500 bg-white dark:border-blue-400 dark:bg-zinc-900">
                            <span class="size-2 rounded-full bg-blue-500 dark:bg-blue-400"></span>
                        </span>
                        @break
                    @case('missed')
                        <span class="flex size-5 shrink-0 items-center justify-center rounded-full bg-red-100 text-red-700 dark:bg-red-400/20 dark:text-red-300">
                            <flux:icon.x-mark class="size-3" />
                        </span>
                        @break
                    @case('canceled')
                        <span class="flex size-5 shrink-0 items-center justify-center rounded-full bg-zinc-100 text-zinc-600 dark:bg-white/10 dark:text-zinc-300">
                            <flux:icon.x-mark class="size-3" />
                        </span>
                        @break
                    @default
                        <span class="size-5 shrink-0 rounded-full border-2 border-dashed border-zinc-300 dark:border-white/20"></span>
                @endswitch

                @unless ($loop->last)
                    <span class="min-h-4 w-px grow bg-zinc-200 dark:bg-white/10"></span>
                @endunless
            </div>

            <div @class(['flex min-w-0 grow items-start gap-2', 'pb-3.5' => ! $loop->last])>
                <div class="min-w-0 grow">
                    <div @class([
                        'text-sm',
                        'font-medium text-zinc-800 dark:text-white' => in_array($step['state'], ['done', 'missed', 'canceled'], true),
                        'font-medium text-blue-800 dark:text-blue-300' => $step['state'] === 'current',
                        'text-zinc-500 dark:text-zinc-400' => $step['state'] === 'upcoming',
                    ])>{{ $step['label'] }}</div>

                    @if ($step['detail'] ?? null)
                        <div class="text-[13px] text-zinc-500 dark:text-zinc-400">{{ $step['detail'] }}</div>
                    @endif
                </div>

                @if ($step['badge'] ?? null)
                    <flux:badge size="sm" color="amber" class="shrink-0" data-test="appointment-details-waiting-for">{{ $step['badge'] }}</flux:badge>
                @endif
            </div>
        </li>
    @endforeach
</ol>
