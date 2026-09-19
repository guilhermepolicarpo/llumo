<?php

use App\Actions\Appointments\PerformAppointmentAction;
use App\Enums\AppointmentAction;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use Carbon\CarbonInterface;
use Flux\Flux;
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

    public string $search = '';

    public string $date = '';

    public string $status = '';

    #[Session('appointments-per-page')]
    public int $perPage = 5;

    public function mount(): void
    {
        Gate::authorize('viewAny', [Appointment::class, Auth::user()->currentTeam]);

        $this->date = today()->toDateString();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedDate(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
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
            ->when(AppointmentStatus::tryFrom($this->status), fn ($query, AppointmentStatus $status) => $query->where('status', $status))
            ->when($this->status === AppointmentStatus::Waiting->value, fn ($query) => $query->orderBy('received_at'))
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

            <flux:select
                wire:model.live="status"
                class="max-w-52"
                :aria-label="__('Status')"
                data-test="appointments-status-select"
            >
                <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
                @foreach (AppointmentStatus::options() as $option)
                    <flux:select.option :value="$option['value']">{{ $option['label'] }}</flux:select.option>
                @endforeach
            </flux:select>

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

    <flux:card class="mt-6 px-4 pt-0 pb-4 [--flux-bleed:1rem]">
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
                        <flux:table.row :key="$appointment->id" @class(['relative', 'cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-800' => $isEditable]) data-test="appointment-row">
                            @unless ($this->filteredDate)
                                <flux:table.cell>
                                    <div class="text-zinc-900 dark:text-white">{{ $appointment->scheduled_on->format('d/m/Y') }}</div>
                                    <div class="text-sm">{{ $appointment->scheduled_on->translatedFormat('l') }}</div>
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
                                @endif

                                <div class="text-zinc-900 dark:text-white">{{ $appointment->assistedPerson->name }}</div>
                                @if ($appointment->assistedPerson->formatted_age)
                                    <div class="text-sm">{{ $appointment->assistedPerson->formatted_age }}</div>
                                @endif
                                @if ($appointment->assistedPerson->address_summary)
                                    <div class="text-sm">{{ $appointment->assistedPerson->address_summary }}</div>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell>
                                <div class="text-zinc-900 dark:text-white">{{ $appointment->appointmentType->name }}</div>
                                <div class="text-sm">{{ $appointment->mode->label() }}</div>
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
                <flux:pagination :paginator="$this->appointments" scroll-to class="contents! @container-normal! *:order-2 [&>:first-child]:order-none [&>:first-child]:font-normal @max-[40rem]:[&>:first-child]:w-full @max-[40rem]:[&>:first-child]:text-center" />

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
            <flux:text class="pt-8 pb-4 text-center text-zinc-500 dark:text-zinc-400">
                @if ($this->search !== '' || $this->date !== '' || $this->status !== '')
                    {{ __('No appointments match your filters.') }}
                @else
                    {{ __('No appointments have been scheduled yet.') }}
                @endif
            </flux:text>
        @endif
    </flux:card>

    <livewire:appointments.delete-appointment-modal @appointment-deleted="$refresh" />
    <livewire:appointments.confirm-appointment-action-modal />
</section>
