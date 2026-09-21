<?php

use App\Actions\Appointments\PerformAppointmentAction;
use App\Enums\AppointmentAction;
use App\Enums\AppointmentMode;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\User;
use Carbon\CarbonInterface;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Js;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Session;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    /**
     * @var list<int>
     */
    public const array PER_PAGE_OPTIONS = [5, 10, 25, 50, 100];

    /**
     * The filter properties the flyout panel controls, mapped to the column each one narrows.
     *
     * @var array<string, string>
     */
    public const array FILTER_GROUPS = [
        'statuses' => 'status',
        'modes' => 'mode',
        'appointmentTypeIds' => 'appointment_type_id',
        'attendantIds' => 'attendant_id',
    ];

    public string $search = '';

    public string $date = '';

    /**
     * @var list<string>
     */
    public array $statuses = [];

    /**
     * @var list<string>
     */
    public array $modes = [];

    /**
     * @var list<string>
     */
    public array $appointmentTypeIds = [];

    /**
     * @var list<string>
     */
    public array $attendantIds = [];

    #[Session('appointments-per-page')]
    public int $perPage = 5;

    public function mount(): void
    {
        Gate::authorize('viewAny', [Appointment::class, Auth::user()->currentTeam]);

        $this->date = today()->toDateString();
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    /**
     * Clear every filter controlled by the flyout panel.
     */
    public function clearFilters(): void
    {
        $this->reset(array_keys(self::FILTER_GROUPS));
        $this->resetPage();
    }

    /**
     * Remove a single selected value from one of the flyout filter groups.
     */
    public function removeFilter(string $group, string|int $value): void
    {
        if (! array_key_exists($group, self::FILTER_GROUPS)) {
            return;
        }

        $this->{$group} = array_values(array_diff($this->{$group}, [$value]));

        $this->resetPage();
    }

    #[On('appointment-action-confirmed')]
    public function perform(PerformAppointmentAction $performAppointmentAction, int $appointmentId, ?AppointmentAction $action): void
    {
        $appointment = Auth::user()->currentTeam->appointments()->findOrFail($appointmentId);

        abort_if($action === null, 404);

        Gate::authorize('perform', $appointment);

        if (! $performAppointmentAction->handle($appointment, $action, Auth::user())) {
            Flux::toast(variant: 'danger', text: __('This action is no longer available for the appointment.'));

            return;
        }

        Flux::toast(variant: 'success', text: __('Appointment moved to :status.', ['status' => $action->toStatus()->label()]));
    }

    #[Computed]
    public function filteredDate(): ?CarbonInterface
    {
        if ($this->date === '' || strtotime($this->date) === false) {
            return null;
        }

        return Date::parse($this->date)->startOfDay();
    }

    /**
     * Get the appointment types available to filter on.
     *
     * @return Collection<int, AppointmentType>
     */
    #[Computed]
    public function appointmentTypes(): Collection
    {
        return Auth::user()->currentTeam->appointmentTypes()->orderBy('name')->get(['id', 'name']);
    }

    /**
     * Get the team members available to filter on as attendants.
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function attendants(): Collection
    {
        return Auth::user()->currentTeam->members()->orderBy('name')->get(['users.id', 'users.name']);
    }

    /**
     * Summarize the attendant selection for the collapsed dropdown trigger.
     */
    #[Computed]
    public function attendantFilterLabel(): string
    {
        return match (count($this->attendantIds)) {
            0 => __('All attendants'),
            1 => $this->filterLabel('attendantIds', (string) $this->attendantIds[0]) ?? __('All attendants'),
            default => __(':count selected', ['count' => count($this->attendantIds)]),
        };
    }

    /**
     * Count how many filter values are currently selected in the flyout panel.
     */
    #[Computed]
    public function activeFilterCount(): int
    {
        return array_sum(array_map(
            fn (string $group): int => count($this->{$group}),
            array_keys(self::FILTER_GROUPS),
        ));
    }

    /**
     * Describe each selected filter value so it can be shown as a removable chip.
     *
     * @return list<array{group: string, value: string, label: string}>
     */
    #[Computed]
    public function activeFilterChips(): array
    {
        $chips = [];

        foreach (array_keys(self::FILTER_GROUPS) as $group) {
            foreach ($this->{$group} as $value) {
                if ($label = $this->filterLabel($group, (string) $value)) {
                    $chips[] = ['group' => $group, 'value' => (string) $value, 'label' => $label];
                }
            }
        }

        return $chips;
    }

    /**
     * Resolve the display label for a selected filter value, or null when it no longer exists.
     */
    private function filterLabel(string $group, string $value): ?string
    {
        return match ($group) {
            'statuses' => AppointmentStatus::tryFrom($value)?->label(),
            'modes' => AppointmentMode::tryFrom($value)?->label(),
            'appointmentTypeIds' => $this->appointmentTypes->firstWhere('id', $value)?->name,
            'attendantIds' => $this->attendants->firstWhere('id', $value)?->name,
        };
    }

    /**
     * @return LengthAwarePaginator<int, Appointment>
     */
    #[Computed]
    public function appointments(): LengthAwarePaginator
    {
        return Auth::user()->currentTeam->appointments()
            ->with(['appointmentType', 'assistedPerson', 'attendant'])
            ->when($this->search !== '', fn ($query) => $query->whereHas(
                'assistedPerson',
                fn ($query) => $query->withTrashed()->whereLike('name', "%{$this->search}%"),
            ))
            ->when($this->filteredDate, fn ($query, CarbonInterface $date) => $query
                ->where('scheduled_on', '>=', $date->toDateString())
                ->where('scheduled_on', '<', $date->copy()->addDay()->toDateString()))
            ->tap(function ($query) {
                foreach (self::FILTER_GROUPS as $group => $column) {
                    $query->when($this->{$group} !== [], fn ($query) => $query->whereIn($column, $this->{$group}));
                }
            })
            ->when($this->statuses === [AppointmentStatus::Waiting->value], fn ($query) => $query->orderBy('received_at'))
            ->orderBy('scheduled_on', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(in_array($this->perPage, self::PER_PAGE_OPTIONS, true) ? $this->perPage : self::PER_PAGE_OPTIONS[0]);
    }

    public function render()
    {
        return $this->view()->title(__('Appointments'));
    }
}; ?>

<section class="w-full">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <flux:heading size="xl">{{ __('Appointments') }}</flux:heading>
            <flux:subheading>
                @if ($this->filteredDate)
                    {{ __('Appointments on :weekday, :date', [
                        'weekday' => $this->filteredDate->translatedFormat('l'),
                        'date' => $this->filteredDate->format('d/m/Y'),
                    ]) }}
                @else
                    {{ __('Appointments scheduled at this Spiritist Center') }}
                @endif
            </flux:subheading>
        </div>

        <div class="flex flex-wrap md:flex-nowrap items-center gap-3">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                :placeholder="__('Search by name...')"
                clearable
                class="max-w-ms"
                data-test="appointments-search-input"
            />

            <flux:input
                type="date"
                wire:model.live="date"
                class="max-w-44"
                :aria-label="__('Date')"
                data-test="appointments-date-input"
            />

            <flux:modal.trigger name="appointments-filters">
                <flux:button icon="funnel" icon-variant="outline" data-test="appointments-filters-button">
                    {{ __('Filters') }}
                    @if ($this->activeFilterCount > 0)
                        <flux:badge size="sm" color="blue" inset="top bottom" data-test="appointments-filters-count">{{ $this->activeFilterCount }}</flux:badge>
                    @endif
                </flux:button>
            </flux:modal.trigger>

            <flux:button
                variant="primary"
                icon="plus"
                :href="route('appointments.create')"
                wire:navigate
                data-test="appointments-new-button"
                >
                {{ __('New appointment') }}
            </flux:button>
        </div>
    </div>

    @if ($this->activeFilterChips !== [])
        <div class="mt-4 flex flex-wrap items-center gap-2" data-test="appointments-active-filters">
            @foreach ($this->activeFilterChips as $chip)
                <flux:badge size="sm" color="zinc">
                    {{ $chip['label'] }}
                    <flux:badge.close
                        wire:click="removeFilter('{{ $chip['group'] }}', {{ Js::from($chip['value']) }})"
                        :aria-label="__('Remove filter :filter', ['filter' => $chip['label']])"
                        data-test="appointments-filter-chip-remove"
                    />
                </flux:badge>
            @endforeach

            <flux:link
                as="button"
                variant="subtle"
                class="cursor-pointer text-sm"
                wire:click="clearFilters"
                data-test="appointments-clear-filters"
                >
                {{ __('Clear filters') }}
            </flux:link>
        </div>
    @endif

    <flux:card class="mt-6 scroll-mt-6 px-4 pt-0 pb-4 [--flux-bleed:1rem]" id="appointments-table">
        @if ($this->appointments->isNotEmpty())
            <flux:table bleed>
                <flux:table.columns>
                    @unless ($this->filteredDate)
                        <flux:table.column data-test="appointments-date-column">{{ __('Date') }}</flux:table.column>
                    @endunless
                    <flux:table.column>{{ __('Assisted person') }}</flux:table.column>
                    <flux:table.column>{{ __('Appointment') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->appointments as $appointment)
                        @php([$primaryActions, $secondaryActions] = collect(AppointmentAction::availableFor($appointment))->partition(fn (AppointmentAction $action) => $action->isPrimary()))
                        @php($isEditable = $appointment->status->isEditable())
                        @php($usesRecord = $appointment->usesRecord())
                        @if ($usesRecord)
                            @php($primaryActions = $primaryActions->reject(fn (AppointmentAction $action) => in_array($action, [AppointmentAction::Start, AppointmentAction::Complete], true)))
                        @endif
                        @php($opensRecord = $usesRecord && $appointment->status->allowsRecordEditing())
                        <flux:table.row :key="$appointment->id" @class(['relative', 'cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-800' => $isEditable || $opensRecord]) data-test="appointment-row">
                            @unless ($this->filteredDate)
                                <flux:table.cell>
                                    <div class="text-[15px] text-zinc-900 dark:text-white">{{ $appointment->scheduled_on->format('d/m/Y') }}</div>
                                    <div>{{ $appointment->scheduled_on->translatedFormat('l') }}</div>
                                </flux:table.cell>
                            @endunless

                            <flux:table.cell>
                                @if ($isEditable)
                                    <a
                                        href="{{ route('appointments.edit', ['appointment' => $appointment]) }}"
                                        wire:navigate
                                        class="absolute inset-0"
                                        aria-label="{{ __('Edit appointment') }}"
                                        data-test="appointment-edit-link"
                                    ></a>
                                @elseif ($opensRecord)
                                    <a
                                        href="{{ route('appointments.attend', ['appointment' => $appointment]) }}"
                                        wire:navigate
                                        class="absolute inset-0"
                                        aria-label="{{ __('Open appointment record') }}"
                                        data-test="appointment-record-link"
                                    ></a>
                                @endif

                                <div class="text-[15px] text-zinc-900 dark:text-white">{{ $appointment->assistedPerson->name }}</div>
                                @if ($appointment->assistedPerson->formatted_age)
                                    <div>{{ $appointment->assistedPerson->formatted_age }}</div>
                                @endif
                                @if ($appointment->assistedPerson->address_summary)
                                    <div>{{ $appointment->assistedPerson->address_summary }}</div>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell>
                                <div class="text-[15px] text-zinc-900 dark:text-white">{{ $appointment->appointmentType->name }}</div>
                                <div>{{ $appointment->mode->label() }}</div>
                            </flux:table.cell>

                            <flux:table.cell>
                                <flux:badge size="sm" :color="$appointment->status->color()" data-test="appointment-status-badge">{{ $appointment->status->label() }}</flux:badge>
                                @if ($appointment->status === AppointmentStatus::Waiting && $appointment->received_at)
                                    <div class="mt-1 text-sm">{{ __('Arrived at :time', ['time' => $appointment->received_at->format('H:i')]) }}</div>
                                @elseif ($appointment->attendant)
                                    <div class="mt-1 text-sm">{{ $appointment->attendant->name }}</div>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell align="end" class="relative z-10">
                                <div class="flex items-center justify-end gap-2">
                                    @if ($usesRecord && $appointment->status->isAttendable())
                                        @php($isWaiting = $appointment->status === AppointmentStatus::Waiting)
                                        <flux:button
                                            size="sm"
                                            :icon="$isWaiting ? 'play' : 'clipboard-document-list'"
                                            :href="route('appointments.attend', ['appointment' => $appointment])"
                                            wire:navigate
                                            data-test="appointment-attend-button"
                                            >
                                            {{ $isWaiting ? AppointmentAction::Start->label() : __('Continue attending') }}
                                        </flux:button>
                                    @endif

                                    @foreach ($primaryActions as $action)
                                        <flux:button
                                            size="sm"
                                            :icon="$action->icon()"
                                            wire:click="perform({{ $appointment->id }}, '{{ $action->value }}')"
                                            wire:loading.attr="disabled"
                                            data-test="appointment-action-{{ $action->value }}"
                                            >
                                            {{ $action->label() }}
                                        </flux:button>
                                    @endforeach

                                    @if ($secondaryActions->isNotEmpty() || $isEditable)
                                        <flux:dropdown position="bottom" align="end">
                                            <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" data-test="appointment-actions-trigger" />
                                            <flux:menu>
                                                @foreach ($secondaryActions as $action)
                                                    @if ($action->needsConfirmation())
                                                        <flux:menu.item
                                                            :icon="$action->icon()"
                                                            wire:click="$dispatch('confirm-appointment-action', { appointmentId: {{ $appointment->id }}, action: '{{ $action->value }}', appointmentDescription: {{ Js::from($appointment->description) }} })"
                                                            data-test="appointment-action-{{ $action->value }}"
                                                            >
                                                            {{ $action->label() }}
                                                        </flux:menu.item>
                                                    @else
                                                        <flux:menu.item
                                                            :icon="$action->icon()"
                                                            wire:click="perform({{ $appointment->id }}, '{{ $action->value }}')"
                                                            data-test="appointment-action-{{ $action->value }}"
                                                            >
                                                            {{ $action->label() }}
                                                        </flux:menu.item>
                                                    @endif
                                                @endforeach

                                                @if ($isEditable)
                                                    @if ($secondaryActions->isNotEmpty())
                                                        <flux:menu.separator />
                                                    @endif

                                                    <flux:menu.item as="a" href="{{ route('appointments.edit', ['appointment' => $appointment]) }}" wire:navigate icon="pencil" data-test="appointment-edit-menu-item">
                                                        {{ __('Edit') }}
                                                    </flux:menu.item>
                                                    <flux:menu.item
                                                        variant="danger"
                                                        icon="trash"
                                                        wire:click="$dispatch('confirm-delete-appointment', { appointmentId: {{ $appointment->id }}, appointmentDescription: @js($appointment->description) })"
                                                        data-test="appointment-delete-menu-item"
                                                        >
                                                        {{ __('Delete') }}
                                                    </flux:menu.item>
                                                @endif
                                            </flux:menu>
                                        </flux:dropdown>
                                    @endif
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>

            <div class="@container flex flex-wrap items-center justify-center gap-3 border-t border-zinc-100 pt-3 dark:border-zinc-700">
                <flux:pagination :paginator="$this->appointments" scroll-to="#appointments-table" class="contents! @container-normal! *:order-2 [&>:first-child]:order-none [&>:first-child]:font-normal @max-[40rem]:[&>:first-child]:w-full @max-[40rem]:[&>:first-child]:text-center" />

                <div class="order-1 flex items-center gap-2 @[40rem]:ms-auto">
                    <flux:text class="whitespace-nowrap text-xs">{{ __('Per page') }}</flux:text>
                    <flux:select wire:model.live="perPage" size="xs" data-test="appointments-per-page-select">
                        @foreach ($this::PER_PAGE_OPTIONS as $option)
                            <flux:select.option :value="$option">{{ $option }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
            </div>
        @else
            <div class="flex flex-col items-center gap-3 pt-8 pb-4">
                <flux:text class="text-center text-zinc-500 dark:text-zinc-400">
                    @if ($this->search !== '' || $this->date !== '' || $this->activeFilterCount > 0)
                        {{ __('No appointments match your filters.') }}
                    @else
                        {{ __('No appointments have been scheduled yet.') }}
                    @endif
                </flux:text>

                @if ($this->activeFilterCount > 0)
                    <flux:button size="sm" wire:click="clearFilters" data-test="appointments-empty-clear-filters">
                        {{ __('Clear filters') }}
                    </flux:button>
                @endif
            </div>
        @endif
    </flux:card>

    <flux:modal name="appointments-filters" flyout >
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Filters') }}</flux:heading>
                <flux:subheading>{{ __('Refine the appointments shown in the list.') }}</flux:subheading>
            </div>

            <flux:checkbox.group wire:model.live.debounce.250ms="statuses" :label="__('Status')" data-test="appointments-status-filter">
                @foreach (AppointmentStatus::options() as $option)
                    <flux:checkbox :value="$option['value']" :label="$option['label']" />
                @endforeach
            </flux:checkbox.group>

            <flux:separator variant="subtle" />

            <flux:checkbox.group wire:model.live.debounce.250ms="modes" :label="__('Mode')" data-test="appointments-mode-filter">
                @foreach (AppointmentMode::options() as $option)
                    <flux:checkbox :value="$option['value']" :label="$option['label']" />
                @endforeach
            </flux:checkbox.group>

            @if ($this->appointmentTypes->isNotEmpty())
                <flux:separator variant="subtle" />

                <flux:checkbox.group wire:model.live.debounce.250ms="appointmentTypeIds" :label="__('Appointment type')" data-test="appointments-type-filter">
                    @foreach ($this->appointmentTypes as $appointmentType)
                        <flux:checkbox :value="(string) $appointmentType->id" :label="$appointmentType->name" />
                    @endforeach
                </flux:checkbox.group>
            @endif

            @if ($this->attendants->isNotEmpty())
                <flux:separator variant="subtle" />

                <flux:field>
                    <flux:label>{{ __('Attendant') }}</flux:label>

                    <flux:dropdown position="bottom" align="start" class="w-full">
                        <button
                            type="button"
                            class="flex h-10 w-full items-center rounded-lg border border-zinc-200 border-b-zinc-300/80 bg-white ps-3 pe-3 text-start text-base shadow-xs sm:text-sm dark:border-white/10 dark:bg-white/10"
                            data-test="appointments-attendant-filter"
                        >
                            <span class="truncate {{ $this->attendantIds === [] ? 'text-zinc-400' : 'text-zinc-700 dark:text-zinc-300' }}">{{ $this->attendantFilterLabel }}</span>
                            <flux:icon name="chevron-up-down" variant="mini" class="ms-auto size-4 text-zinc-400" />
                        </button>

                        <flux:menu class="max-h-72 min-w-(--button-width) overflow-y-auto">
                            <flux:menu.checkbox.group wire:model.live.debounce.250ms="attendantIds">
                                @foreach ($this->attendants as $attendant)
                                    <flux:menu.checkbox :value="(string) $attendant->id" wire:key="attendant-{{ $attendant->id }}">{{ $attendant->name }}</flux:menu.checkbox>
                                @endforeach
                            </flux:menu.checkbox.group>
                        </flux:menu>
                    </flux:dropdown>
                </flux:field>
            @endif

            <div class="flex justify-end gap-2 pt-2">
                <flux:button
                    variant="subtle"
                    wire:click="clearFilters"
                    :disabled="$this->activeFilterCount === 0"
                    data-test="appointments-flyout-clear-filters"
                    >
                    {{ __('Clear filters') }}
                </flux:button>

                <flux:modal.close>
                    <flux:button variant="primary" data-test="appointments-flyout-done">{{ __('Done') }}</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>

    <livewire:appointments.delete-appointment-modal @appointment-deleted="$refresh" />
    <livewire:appointments.confirm-appointment-action-modal />
</section>
