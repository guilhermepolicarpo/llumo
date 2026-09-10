<?php

use App\Models\AssistedPerson;
use App\Rules\Phone;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public function mount(): void
    {
        Gate::authorize('viewAny', [AssistedPerson::class, Auth::user()->currentTeam]);
    }

    /**
     * @return Collection<int, AssistedPerson>
     */
    #[Computed]
    public function assistedPeople(): Collection
    {
        return Auth::user()->currentTeam->assistedPeople()->orderBy('created_at', 'desc')->get();
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
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Name') }}</flux:table.column>
                    <flux:table.column>{{ __('Address') }}</flux:table.column>
                    <flux:table.column>{{ __('Age') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->assistedPeople as $person)
                        @php
                            $primaryClass = $loop->first
                                ? 'font-semibold text-zinc-900 dark:text-white'
                                : 'text-zinc-600 dark:text-zinc-300';
                            $contact = $person->phone ? Phone::format($person->phone) : $person->email;
                        @endphp

                        <flux:table.row class="relative cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-800" data-test="assisted-person-row">
                            <flux:table.cell>
                                <a
                                    href="{{ route('assisted-people.edit', ['assistedPerson' => $person]) }}"
                                    wire:navigate
                                    class="absolute inset-0"
                                    aria-label="{{ __('Edit :name', ['name' => $person->name]) }}"
                                    data-test="assisted-person-edit-link"
                                ></a>

                                <div class="{{ $primaryClass }}">{{ $person->name }}</div>
                                @if ($contact)
                                    <div class="text-sm text-zinc-500 dark:text-zinc-400">{{ $contact }}</div>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell>
                                <div class="{{ $primaryClass }}">{{ $person->address_line ?? '—' }}</div>
                                <div class="text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ $person->address_city_line ?? '—' }}
                                </div>
                            </flux:table.cell>

                            <flux:table.cell>
                                <div class="{{ $primaryClass }}">{{ $person->formatted_age ?? '—' }}</div>
                                <div class="text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ $person->birth_date?->format('d/m/Y') ?? '—' }}
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @else
            <flux:text class="py-8 text-center text-zinc-500 dark:text-zinc-400">
                {{ __('No assisted people have been registered yet.') }}
            </flux:text>
        @endif
    </flux:card>
</section>
