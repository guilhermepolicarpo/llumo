<?php

use App\Models\AssistedPerson;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public function mount(): void
    {
        Gate::authorize('viewAny', [AssistedPerson::class, Auth::user()->currentTeam]);
    }

    /**
     * @return LengthAwarePaginator<int, AssistedPerson>
     */
    #[Computed]
    public function assistedPeople(): LengthAwarePaginator
    {
        return Auth::user()->currentTeam->assistedPeople()->orderBy('created_at', 'desc')->paginate(10);
    }

    #[On('assisted-person-deleted')]
    public function refreshAssistedPeople(): void
    {
        // Listening is enough: it forces a re-render, and the computed property recomputes fresh each request.
    }

    public function render()
    {
        return $this->view()->title(__('Assisted people'));
    }
}; ?>

<section class="w-full">
    <div class="flex items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Assisted people') }}</flux:heading>
            <flux:subheading>{{ __('People assisted by this Spiritist Center') }}</flux:subheading>
        </div>
        
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

    <flux:separator variant="subtle" class="my-4" />

    <flux:card class="mt-6">
        @if ($this->assistedPeople->isNotEmpty())
            <flux:table bleed :paginate="$this->assistedPeople" pagination:scroll-to >
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

                                <div class="text-zinc-900 dark:text-white">{{ $person->name }}</div>
                                @if ($person->contact)
                                    <div class="text-sm">{{ $person->contact }}</div>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell>
                                <div class="text-zinc-900 dark:text-white">{{ $person->address_line ?? '—' }}</div>
                                <div class="text-sm">
                                    {{ $person->address_city_line ?? '—' }}
                                </div>
                            </flux:table.cell>

                            <flux:table.cell>
                                <div class="text-zinc-900 dark:text-white">{{ $person->formatted_age ?? '—' }}</div>
                                <div class="text-sm">
                                    {{ $person->birth_date?->format('d/m/Y') ?? '—' }}
                                </div>
                            </flux:table.cell>

                            <flux:table.cell class="relative z-10">
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
                                            class="cursor-pointer"
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
        @else
            <flux:text class="py-4 text-center text-zinc-500 dark:text-zinc-400">
                {{ __('No assisted people have been registered yet.') }}
            </flux:text>
        @endif
    </flux:card>

    <livewire:pages::assisted-people.delete-assisted-person-modal />
</section>
