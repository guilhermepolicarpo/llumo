<?php

use App\Models\AssistedPerson;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
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

    #[Session('assisted-people-per-page')]
    public int $perPage = 5;

    public function mount(): void
    {
        Gate::authorize('viewAny', [AssistedPerson::class, Auth::user()->currentTeam]);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, AssistedPerson>
     */
    #[Computed]
    public function assistedPeople(): LengthAwarePaginator
    {
        return Auth::user()->currentTeam->assistedPeople()
            ->when($this->search !== '', fn ($query) => $query->whereLike('name', "%{$this->search}%"))
            ->orderBy('created_at', 'desc')
            ->paginate(in_array($this->perPage, self::PER_PAGE_OPTIONS, true) ? $this->perPage : self::PER_PAGE_OPTIONS[0]);
    }

    public function render()
    {
        return $this->view()->title(__('Assisted people'));
    }
}; ?>

<section class="w-full">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('Assisted people') }}</flux:heading>
            <flux:subheading>{{ __('People assisted by this Spiritist Center') }}</flux:subheading>
        </div>

        <div class="flex items-center gap-3">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                :placeholder="__('Search by name...')"
                clearable
                class="max-sm:flex-1 sm:w-64"
                data-test="assisted-people-search-input"
            />

            <flux:button
                variant="primary"
                icon="plus"
                :href="route('assisted-people.create')"
                wire:navigate
                data-test="assisted-people-new-button"
                >
                {{ __('New assisted person') }}
            </flux:button>
        </div>
    </div>

    <flux:card class="mt-6 scroll-mt-6 px-4 pt-0 pb-4 [--flux-bleed:1rem]" id="assisted-people-table">
        @if ($this->assistedPeople->isNotEmpty())
            <flux:table bleed>
                <flux:table.columns>
                    <flux:table.column>{{ __('Name') }}</flux:table.column>
                    <flux:table.column>{{ __('Address') }}</flux:table.column>
                    <flux:table.column>{{ __('Age') }}</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->assistedPeople as $person)
                        <flux:table.row :key="$person->id" class="relative cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-800" data-test="assisted-person-row">
                            <flux:table.cell>
                                <a
                                    href="{{ route('assisted-people.edit', ['assistedPerson' => $person]) }}"
                                    wire:navigate
                                    class="absolute inset-0"
                                    aria-label="{{ __('Edit :name', ['name' => $person->name]) }}"
                                    data-test="assisted-person-edit-link"
                                ></a>

                                <div class="text-[15px] text-zinc-900 dark:text-white">{{ $person->name }}</div>
                                @if ($person->email)
                                    <div>{{ $person->email }}</div>
                                @endif
                                @if ($person->formatted_phone)
                                    <div>{{ $person->formatted_phone }}</div>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell>
                                <div class="text-[15px] text-zinc-900 dark:text-white">{{ $person->address_line ?? '—' }}</div>
                                <div>
                                    {{ $person->address_city_line ?? '—' }}
                                </div>
                            </flux:table.cell>

                            <flux:table.cell>
                                <div class="text-[15px] text-zinc-900 dark:text-white">{{ $person->formatted_age ?? '—' }}</div>
                                <div>
                                    {{ $person->birth_date?->format('d/m/Y') ?? '—' }}
                                </div>
                            </flux:table.cell>

                            <flux:table.cell align="end" class="relative z-10">
                                <flux:dropdown position="bottom" align="end">
                                    <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" data-test="assisted-person-actions-trigger" />
                                    <flux:menu>
                                        <flux:menu.item as="a" href="{{ route('assisted-people.edit', ['assistedPerson' => $person]) }}" wire:navigate icon="pencil" data-test="assisted-person-edit-menu-item">
                                            {{ __('Edit') }}
                                        </flux:menu.item>
                                        <flux:menu.item
                                            variant="danger"
                                            icon="trash"
                                            wire:click="$dispatch('confirm-delete-assisted-person', { assistedPersonId: {{ $person->id }}, assistedPersonName: @js($person->name) })"
                                            data-test="assisted-person-delete-menu-item"
                                            >
                                            {{ __('Delete') }}
                                        </flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>

            <div class="@container flex flex-wrap items-center justify-center gap-3 border-t border-zinc-100 pt-3 dark:border-zinc-700">
                <flux:pagination :paginator="$this->assistedPeople" scroll-to="#assisted-people-table" class="contents! @container-normal! *:order-2 [&>:first-child]:order-none [&>:first-child]:font-normal @max-[40rem]:[&>:first-child]:w-full @max-[40rem]:[&>:first-child]:text-center" />

                <div class="order-1 flex items-center gap-2 @[40rem]:ms-auto">
                    <flux:text class="whitespace-nowrap text-xs">{{ __('Per page') }}</flux:text>
                    <flux:select wire:model.live="perPage" size="xs" data-test="assisted-people-per-page-select">
                        @foreach ($this::PER_PAGE_OPTIONS as $option)
                            <flux:select.option :value="$option">{{ $option }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
            </div>
        @else
            <flux:text class="pt-8 pb-4 text-center text-zinc-500 dark:text-zinc-400">
                @if ($this->search !== '')
                    {{ __('No assisted people match your search.') }}
                @else
                    {{ __('No assisted people have been registered yet.') }}
                @endif
            </flux:text>
        @endif
    </flux:card>

    <livewire:pages::assisted-people.delete-assisted-person-modal @assisted-person-deleted="$refresh" />
</section>
