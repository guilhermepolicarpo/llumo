<?php

use App\Enums\AppointmentAction;
use App\Enums\AppointmentStatus;
use App\Enums\BookLoanStatus;
use App\Models\Appointment;
use App\Models\BookLoan;
use App\Models\Team;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    /**
     * The bar color of each status counted for today, in the order the progress bar shows them.
     *
     * @var array<string, string>
     */
    public const array TODAY_SEGMENTS = [
        AppointmentStatus::Completed->value => 'bg-green-500',
        AppointmentStatus::InProgress->value => 'bg-blue-500',
        AppointmentStatus::Waiting->value => 'bg-amber-400',
        AppointmentStatus::Scheduled->value => 'bg-zinc-300 dark:bg-zinc-600',
        AppointmentStatus::NoShow->value => 'bg-red-500',
    ];

    /**
     * How many days after today the upcoming appointments cover; the chart lays them out in seven columns.
     */
    public const int UPCOMING_DAYS = 7;

    /**
     * How many overdue loans the library card lists.
     */
    public const int OVERDUE_LOANS_SHOWN = 3;

    /**
     * After how many minutes of waiting an assisted person is highlighted in the queue.
     */
    public const int LONG_WAIT_MINUTES = 30;

    /**
     * Get the current team, whose data the dashboard summarizes.
     */
    #[Computed]
    public function team(): Team
    {
        return Auth::user()->currentTeam;
    }

    #[Computed]
    public function canViewAppointments(): bool
    {
        return Auth::user()->can('viewAny', [Appointment::class, $this->team]);
    }

    #[Computed]
    public function canViewLoans(): bool
    {
        return Auth::user()->can('viewAny', [BookLoan::class, $this->team]);
    }

    /**
     * Count today's appointments by status, leaving the canceled ones out.
     *
     * @return array<string, int>
     */
    #[Computed]
    public function todayCounts(): array
    {
        return $this->team->appointments()
            ->scheduledOn(today())
            ->takingPlace()
            ->toBase()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn (mixed $total): int => (int) $total)
            ->all();
    }

    /**
     * Sum today's appointments: all of them, the completed ones, and those whose attendance is not over yet.
     *
     * @return array{total: int, completed: int, remaining: int}
     */
    #[Computed]
    public function todaySummary(): array
    {
        $countOf = fn (AppointmentStatus $status): int => $this->todayCounts[$status->value] ?? 0;

        return [
            'total' => array_sum($this->todayCounts),
            'completed' => $countOf(AppointmentStatus::Completed),
            'remaining' => array_sum(array_map($countOf, AppointmentStatus::open())),
        ];
    }

    /**
     * Get today's assisted people at the reception: those being attended first, then those waiting by order of arrival.
     *
     * @return Collection<int, Appointment>
     */
    #[Computed]
    public function queue(): Collection
    {
        return $this->team->appointments()
            ->scheduledOn(today())
            ->whereIn('status', [AppointmentStatus::InProgress, AppointmentStatus::Waiting])
            ->with(['assistedPerson', 'appointmentType'])
            ->orderByStatus('desc')
            ->orderBy('received_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * Count today's appointments waiting to be attended whose attendance sheet can be printed, from the queue already loaded.
     */
    #[Computed]
    public function printableSheetCount(): int
    {
        return $this->queue
            ->filter(fn (Appointment $appointment): bool => $appointment->status === AppointmentStatus::Waiting && $appointment->usesRecord())
            ->count();
    }

    /**
     * Count the appointments from previous days whose attendance was never entered in the system.
     */
    #[Computed]
    public function pendingCount(): int
    {
        return $this->team->appointments()->pending()->count();
    }

    /**
     * Count the appointments of each of the next days, keyed by date, including the days with none.
     *
     * @return array<string, int>
     */
    #[Computed]
    public function upcomingCounts(): array
    {
        $counts = $this->team->appointments()
            ->scheduledBetween(today()->addDay(), today()->addDays(self::UPCOMING_DAYS + 1))
            ->takingPlace()
            ->toBase()
            ->selectRaw('scheduled_on, count(*) as total')
            ->groupBy('scheduled_on')
            ->pluck('total', 'scheduled_on')
            ->mapWithKeys(fn (mixed $total, string $day): array => [Date::parse($day)->toDateString() => (int) $total]);

        return collect(range(1, self::UPCOMING_DAYS))
            ->mapWithKeys(fn (int $offset): array => [$day = today()->addDays($offset)->toDateString() => $counts->get($day, 0)])
            ->all();
    }

    /**
     * Count the open loans whose due date has passed.
     */
    #[Computed]
    public function overdueLoanCount(): int
    {
        return $this->team->bookLoans()->overdue()->count();
    }

    /**
     * Count the open loans due from today up to the next few days.
     */
    #[Computed]
    public function dueSoonLoanCount(): int
    {
        return $this->team->bookLoans()->dueSoon()->count();
    }

    /**
     * Get the overdue loans, the longest overdue first.
     *
     * @return Collection<int, BookLoan>
     */
    #[Computed]
    public function overdueLoans(): Collection
    {
        return $this->team->bookLoans()
            ->overdue()
            ->with(['assistedPerson', 'copy.book'])
            ->orderBy('due_on')
            ->orderBy('id')
            ->limit(self::OVERDUE_LOANS_SHOWN)
            ->get();
    }

    /**
     * List what needs someone's attention, leaving out what the user cannot see and what has nothing to show.
     *
     * @return list<array{key: string, count: int, title: string, detail: string, href: string, newTab: bool, color: string}>
     */
    #[Computed]
    public function attentionItems(): array
    {
        $items = [];

        if ($this->canViewAppointments && $this->printableSheetCount > 0) {
            $items[] = [
                'key' => 'sheets',
                'count' => $this->printableSheetCount,
                'title' => __('Attendance sheets to print'),
                'detail' => __('Assisted people waiting today'),
                'href' => route('appointments.attendance-sheets', ['date' => today()->toDateString()]),
                'newTab' => true,
                'color' => 'blue',
            ];
        }

        if ($this->canViewAppointments && $this->pendingCount > 0) {
            $items[] = [
                'key' => 'pending',
                'count' => $this->pendingCount,
                'title' => __('Appointments awaiting entry'),
                'detail' => __('From previous days'),
                'href' => route('appointments.index', ['pending' => 1]),
                'newTab' => false,
                'color' => 'amber',
            ];
        }

        if ($this->canViewLoans && $this->overdueLoanCount > 0) {
            $items[] = [
                'key' => 'overdue-loans',
                'count' => $this->overdueLoanCount,
                'title' => __('Overdue loans'),
                'detail' => __('Past the due date'),
                'href' => route('library.loans.index', ['status' => BookLoanStatus::Overdue->value]),
                'newTab' => false,
                'color' => 'red',
            ];
        }

        if ($this->canViewLoans && $this->dueSoonLoanCount > 0) {
            $items[] = [
                'key' => 'due-soon-loans',
                'count' => $this->dueSoonLoanCount,
                'title' => __('Loans due soon'),
                'detail' => trans_choice('Within the next :count day|Within the next :count days', BookLoan::DUE_SOON_DAYS),
                'href' => route('library.loans.index'),
                'newTab' => false,
                'color' => 'zinc',
            ];
        }

        return $items;
    }

    /**
     * Count the minutes an appointment at the reception has been waiting, or in attendance once it started.
     */
    public function minutesAtReception(Appointment $appointment): int
    {
        $since = $appointment->status === AppointmentStatus::InProgress ? $appointment->started_at : $appointment->received_at;

        return $since === null ? 0 : (int) $since->diffInMinutes(now());
    }

    public function render()
    {
        return $this->view()->title(__('Dashboard'));
    }
}; ?>

<section class="w-full">
    <livewire:pages::teams.pending-invitations-modal />

    <div class="mx-auto flex max-w-6xl flex-col gap-6">
        <x-page-header :heading="__('Dashboard')" :subheading="Str::ucfirst(today()->translatedFormat('l, d \d\e F \d\e Y'))" :separator="false" class="mb-0!">
            <div class="flex flex-wrap items-center gap-2">
                @can('create', [BookLoan::class, $this->team])
                    <flux:button icon="book-open" icon:variant="outline" :href="route('library.loans.create')" wire:navigate data-test="dashboard-lend-book-button">
                        {{ __('Lend a book') }}
                    </flux:button>
                @endcan

                @can('create', [Appointment::class, $this->team])
                    <flux:button variant="primary" icon="plus" :href="route('appointments.create')" wire:navigate data-test="dashboard-new-appointment-button">
                        {{ __('New appointment') }}
                    </flux:button>
                @endcan
            </div>
        </x-page-header>

        <div class="grid items-start gap-6 lg:grid-cols-3">
            @if ($this->canViewAppointments)
                <flux:card class="lg:col-span-2" data-test="dashboard-today">
                    <flux:card.header>
                        <flux:card.heading size="lg" level="2">{{ __('Today at the center') }}</flux:card.heading>

                        <flux:card.actions>
                            <flux:button size="sm" variant="ghost" icon="calendar-days" icon:variant="outline" :href="route('appointments.index')" wire:navigate data-test="dashboard-today-link">
                                {{ __('Open schedule') }}
                            </flux:button>
                        </flux:card.actions>
                    </flux:card.header>

                    <flux:card.body>
                        @if ($this->todaySummary['total'] > 0)
                            <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                                <flux:heading size="2xl" class="text-4xl! tabular-nums" data-test="dashboard-today-progress">{{ __(':completed of :total', ['completed' => $this->todaySummary['completed'], 'total' => $this->todaySummary['total']]) }}</flux:heading>
                                <flux:text size="lg">
                                    {{ trans_choice('appointment completed|appointments completed', $this->todaySummary['total']) }} · {{ __(':count still to come', ['count' => $this->todaySummary['remaining']]) }}
                                </flux:text>
                            </div>

                            <div class="mt-4 flex h-3 gap-0.5 overflow-hidden rounded-md" aria-hidden="true">
                                @foreach (self::TODAY_SEGMENTS as $status => $barClass)
                                    @if (($this->todayCounts[$status] ?? 0) > 0)
                                        <div class="{{ $barClass }}" style="flex: {{ $this->todayCounts[$status] }} 1 0%"></div>
                                    @endif
                                @endforeach
                            </div>

                            <ul class="mt-3 flex flex-wrap gap-x-5 gap-y-2">
                                @foreach (self::TODAY_SEGMENTS as $status => $barClass)
                                    <li class="flex items-center gap-1.5" data-test="dashboard-today-{{ $status }}">
                                        <span class="size-2.5 rounded-sm {{ $barClass }}" aria-hidden="true"></span>
                                        <flux:text inline>{{ AppointmentStatus::from($status)->label() }}</flux:text>
                                        <flux:text inline variant="strong" class="font-semibold tabular-nums">{{ $this->todayCounts[$status] ?? 0 }}</flux:text>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <flux:text>{{ __('No appointments today.') }}</flux:text>
                        @endif

                        <flux:separator variant="subtle" class="my-5" />

                        <flux:card.header>
                            <flux:card.heading level="3">{{ __('At the reception now') }}</flux:card.heading>
                            @if ($this->queue->isNotEmpty())
                                <flux:card.subheading>{{ __('by order of arrival') }}</flux:card.subheading>
                            @endif
                        </flux:card.header>

                        <div class="divide-y divide-zinc-800/10 dark:divide-white/10" data-test="dashboard-queue">
                            @forelse ($this->queue as $appointment)
                                @php
                                    $minutes = $this->minutesAtReception($appointment);
                                    $isWaiting = $appointment->status === AppointmentStatus::Waiting;
                                    $isLongWait = $isWaiting && $minutes >= self::LONG_WAIT_MINUTES;
                                @endphp

                                <div wire:key="queue-{{ $appointment->id }}" class="flex flex-wrap items-center gap-x-4 gap-y-2 py-3 last:pb-0" data-test="dashboard-queue-row">
                                    <div class="min-w-0 flex-[1_1_14rem]">
                                        <flux:heading>{{ $appointment->assistedPerson->name }}</flux:heading>
                                        <flux:text>{{ $appointment->appointmentType->name }} · {{ $appointment->mode->label() }}</flux:text>
                                    </div>

                                    <flux:badge size="sm" :color="$appointment->status->color()">{{ $appointment->status->label() }}</flux:badge>

                                    <flux:text inline :color="$isLongWait ? 'amber' : null" @class(['w-16 text-end tabular-nums', 'font-medium' => $isLongWait])>
                                        {{ __(':count min', ['count' => $minutes]) }}
                                    </flux:text>

                                    <div class="w-32 text-end">
                                        @if ($appointment->usesRecord() && $appointment->isAttendable())
                                            <flux:button
                                                size="sm"
                                                :variant="$isWaiting ? 'primary' : 'outline'"
                                                :icon="$isWaiting ? 'play' : 'clipboard-document-list'"
                                                icon:variant="outline"
                                                :href="route('appointments.attend', ['appointment' => $appointment])"
                                                wire:navigate
                                                data-test="dashboard-queue-attend"
                                                >
                                                {{ $isWaiting ? AppointmentAction::Start->label() : __('Continue') }}
                                            </flux:button>
                                        @else
                                            <flux:button size="sm" variant="ghost" :href="route('appointments.index')" wire:navigate data-test="dashboard-queue-schedule">
                                                {{ __('View in schedule') }}
                                            </flux:button>
                                        @endif
                                    </div>
                                </div>
                            @empty
                                <flux:text class="py-2">{{ __('Nobody is at the reception right now.') }}</flux:text>
                            @endforelse
                        </div>
                    </flux:card.body>
                </flux:card>
            @endif

            <div class="flex min-w-0 flex-col gap-6">
                <flux:card data-test="dashboard-attention">
                    <flux:card.header>
                        <flux:card.heading size="lg" level="2">{{ __('Needs attention') }}</flux:card.heading>
                    </flux:card.header>

                    <flux:card.body>
                        @forelse ($this->attentionItems as $item)
                            <a
                                href="{{ $item['href'] }}"
                                @if ($item['newTab']) target="_blank" @else wire:navigate @endif
                                class="-mx-2 flex min-h-14 items-center gap-3 rounded-lg px-2 py-2 hover:bg-zinc-800/5 dark:hover:bg-white/10"
                                data-test="dashboard-attention-{{ $item['key'] }}"
                                >
                                <flux:badge size="lg" :color="$item['color']" class="min-w-9 justify-center font-semibold tabular-nums">{{ $item['count'] }}</flux:badge>
                                <span class="min-w-0 flex-1">
                                    <flux:heading>{{ $item['title'] }}</flux:heading>
                                    <flux:text>{{ $item['detail'] }}</flux:text>
                                </span>
                                <flux:icon :name="$item['newTab'] ? 'arrow-top-right-on-square' : 'chevron-right'" class="size-4 text-zinc-400 dark:text-zinc-500" />
                            </a>
                        @empty
                            <div class="flex items-center gap-3" data-test="dashboard-attention-empty">
                                <flux:icon.check-circle class="size-5 text-green-600 dark:text-green-400" />
                                <flux:text>{{ __('All caught up.') }}</flux:text>
                            </div>
                        @endforelse
                    </flux:card.body>
                </flux:card>

                @if ($this->canViewLoans && $this->overdueLoanCount > 0)
                    <flux:card data-test="dashboard-overdue-loans">
                        <flux:card.header>
                            <flux:card.heading size="lg" level="2">{{ __('Overdue loans') }}</flux:card.heading>

                            <flux:card.actions>
                                <flux:button size="sm" variant="ghost" :href="route('library.loans.index', ['status' => BookLoanStatus::Overdue->value])" wire:navigate data-test="dashboard-overdue-loans-link">
                                    {{ __('View all') }}
                                </flux:button>
                            </flux:card.actions>
                        </flux:card.header>

                        <flux:card.body>
                            <flux:table bleed>
                                <flux:table.rows>
                                    @foreach ($this->overdueLoans as $loan)
                                        <flux:table.row :key="'overdue-loan-'.$loan->id" data-test="dashboard-loan-row">
                                            <flux:table.cell class="whitespace-normal">
                                                <flux:heading>{{ $loan->copy->book->title }}</flux:heading>
                                                <flux:text>{{ $loan->assistedPerson->name }}</flux:text>
                                            </flux:table.cell>
                                            <flux:table.cell align="end" class="text-red-600 dark:text-red-400">{{ $loan->dueHint() }}</flux:table.cell>
                                        </flux:table.row>
                                    @endforeach
                                </flux:table.rows>
                            </flux:table>
                        </flux:card.body>
                    </flux:card>
                @endif
            </div>
        </div>

        @if ($this->canViewAppointments)
            @php
                $busiestDayCount = max(1, ...array_values($this->upcomingCounts));
            @endphp

            <flux:card data-test="dashboard-upcoming">
                <flux:card.header>
                    <flux:card.heading size="lg" level="2">{{ trans_choice('Next :count day|Next :count days', self::UPCOMING_DAYS) }}</flux:card.heading>
                    <flux:card.subheading>{{ trans_choice(':count scheduled appointment|:count scheduled appointments', array_sum($this->upcomingCounts)) }}</flux:card.subheading>
                </flux:card.header>

                <flux:card.body>
                    <ul class="grid grid-cols-7 gap-1 sm:gap-2">
                        @foreach ($this->upcomingCounts as $day => $count)
                            @php($date = Date::parse($day))
                            <li wire:key="upcoming-{{ $day }}">
                                <a
                                    href="{{ route('appointments.index', ['date' => $day]) }}"
                                    wire:navigate
                                    class="group flex flex-col items-center gap-1.5 rounded-lg pb-1 hover:bg-zinc-800/5 dark:hover:bg-white/10"
                                    aria-label="{{ Str::ucfirst($date->translatedFormat('l, d/m')) }}: {{ trans_choice(':count scheduled appointment|:count scheduled appointments', $count) }}"
                                    data-test="dashboard-upcoming-day"
                                    >
                                    <span class="flex h-28 w-full flex-col items-center justify-end gap-1 border-b border-zinc-300 dark:border-zinc-600">
                                        <flux:text inline :variant="$count > 0 ? 'strong' : 'subtle'" class="font-semibold tabular-nums">{{ $count > 0 ? $count : '—' }}</flux:text>
                                        @if ($count > 0)
                                            <span class="w-3/5 max-w-10 rounded-t bg-blue-500 group-hover:bg-blue-600" style="height: {{ max(4, (int) round($count / $busiestDayCount * 84)) }}px"></span>
                                        @endif
                                    </span>
                                    <flux:text inline>{{ Str::ucfirst($date->translatedFormat('D')) }}</flux:text>
                                    <flux:text inline size="sm" variant="subtle" class="-mt-1.5">{{ $date->format('d/m') }}</flux:text>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </flux:card.body>
            </flux:card>
        @endif
    </div>
</section>
