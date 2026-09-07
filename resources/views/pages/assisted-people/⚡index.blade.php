<?php

use App\Models\AssistedPerson;
use App\Models\Team;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Assisted people')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    public ?int $deletingId = null;

    public function mount(): void
    {
        Gate::authorize('viewAny', [AssistedPerson::class, $this->team]);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function confirmDelete(int $assistedPersonId): void
    {
        $this->deletingId = $assistedPersonId;

        $this->dispatch('modal-show', name: 'delete-assisted-person');
    }

    public function deleteAssistedPerson(): void
    {
        $assistedPerson = $this->team->assistedPeople()->findOrFail($this->deletingId);

        $assistedPerson->setRelation('team', $this->team);

        Gate::authorize('delete', $assistedPerson);

        $assistedPerson->delete();

        $this->deletingId = null;

        $this->dispatch('modal-close', name: 'delete-assisted-person');

        unset($this->assistedPeople);

        Flux::toast(variant: 'success', text: __('Assisted person deleted.'));
    }

    #[Computed]
    public function team(): Team
    {
        return Auth::user()->currentTeam;
    }

    /**
     * @return LengthAwarePaginator<int, AssistedPerson>
     */
    #[Computed]
    public function assistedPeople(): LengthAwarePaginator
    {
        return $this->team->assistedPeople()
            ->search($this->search)
            ->orderBy('name')
            ->paginate(15);
    }

    #[Computed]
    public function deletingPerson(): ?AssistedPerson
    {
        return $this->deletingId === null
            ? null
            : $this->assistedPeople->firstWhere('id', $this->deletingId);
    }

    #[Computed]
    public function canCreate(): bool
    {
        return Gate::allows('create', [AssistedPerson::class, $this->team]);
    }

    #[Computed]
    public function canDelete(): bool
    {
        return Gate::allows('deleteAny', [AssistedPerson::class, $this->team]);
    }
}; ?>

<section class="w-full">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Assisted people') }}</flux:heading>
            <flux:subheading>{{ __('Manage the people assisted by this house') }}</flux:subheading>
        </div>

        @if ($this->canCreate)
            <flux:button variant="primary" icon="plus" :href="route('assisted-people.create')" wire:navigate data-test="assisted-person-create-button">
                {{ __('New assisted person') }}
            </flux:button>
        @endif
    </div>

    <div class="mt-6 max-w-md">
        <flux:input
            wire:model.live.debounce.300ms="search"
            icon="magnifying-glass"
            :placeholder="__('Search by name, email or phone')"
            :label="__('Search')"
            class:label="sr-only"
            data-test="assisted-people-search"
        />
    </div>

    <div class="mt-6">
        @if ($this->assistedPeople->isEmpty())
            <flux:text class="py-12 text-center text-zinc-500 dark:text-zinc-400" data-test="assisted-people-empty">
                @if ($this->search !== '')
                    {{ __('No assisted person matches your search.') }}
                @else
                    {{ __('No assisted person registered yet.') }}
                @endif
            </flux:text>
        @else
            <flux:table :paginate="$this->assistedPeople">
                <flux:table.columns>
                    <flux:table.column>{{ __('Name') }}</flux:table.column>
                    <flux:table.column>{{ __('Age') }}</flux:table.column>
                    <flux:table.column>{{ __('Phone') }}</flux:table.column>
                    <flux:table.column>{{ __('City') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Actions') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->assistedPeople as $assistedPerson)
                        <flux:table.row :key="'assisted-person-'.$assistedPerson->id" data-test="assisted-person-row">
                            <flux:table.cell class="font-medium">{{ $assistedPerson->name }}</flux:table.cell>
                            <flux:table.cell>{{ $assistedPerson->age }}</flux:table.cell>
                            <flux:table.cell>{{ $assistedPerson->phone ?: '—' }}</flux:table.cell>
                            <flux:table.cell>{{ $assistedPerson->cityLine ?: '—' }}</flux:table.cell>
                            <flux:table.cell align="end">
                                <div class="flex items-center justify-end gap-1">
                                    <flux:tooltip :content="__('Edit')">
                                        <flux:button
                                            variant="ghost"
                                            size="sm"
                                            icon="pencil"
                                            :href="route('assisted-people.edit', $assistedPerson)"
                                            wire:navigate
                                            data-test="assisted-person-edit-button"
                                        />
                                    </flux:tooltip>

                                    @if ($this->canDelete)
                                        <flux:tooltip :content="__('Delete')">
                                            <flux:button
                                                variant="ghost"
                                                size="sm"
                                                icon="trash"
                                                wire:click="confirmDelete({{ $assistedPerson->id }})"
                                                data-test="assisted-person-delete-button"
                                            />
                                        </flux:tooltip>
                                    @endif
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>

            @if ($this->canDelete)
                <flux:modal name="delete-assisted-person" focusable class="max-w-lg">
                    <form wire:submit="deleteAssistedPerson" class="space-y-6">
                        <div>
                            <flux:heading size="lg">{{ __('Are you sure?') }}</flux:heading>
                            <flux:subheading>
                                {{ __('This will remove ":name" from the list of assisted people.', ['name' => $this->deletingPerson?->name]) }}
                            </flux:subheading>
                        </div>

                        <div class="flex justify-end space-x-2 rtl:space-x-reverse">
                            <flux:modal.close>
                                <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                            </flux:modal.close>

                            <flux:button variant="danger" type="submit" data-test="assisted-person-delete-confirm">
                                {{ __('Delete') }}
                            </flux:button>
                        </div>
                    </form>
                </flux:modal>
            @endif
        @endif
    </div>
</section>
