<?php

use App\Actions\Catalogs\CreateCatalogEntry;
use App\Actions\Catalogs\UpdateCatalogEntry;
use App\Enums\Catalog;
use App\Models\Team;
use App\Rules\CatalogRules;
use Flux\Flux;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Session;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    /**
     * @var list<int>
     */
    public const array PER_PAGE_OPTIONS = [10, 25, 50, 100];

    #[Locked]
    public Catalog $catalog;

    public string $search = '';

    #[Session('catalogs-per-page')]
    public int $perPage = 25;

    public string $name = '';

    #[Locked]
    public ?int $editingId = null;

    #[Locked]
    public ?int $deletingId = null;

    public string $deletingName = '';

    public function mount(Catalog $catalog): void
    {
        $this->catalog = $catalog;

        Gate::authorize('viewAny', [$catalog->modelClass(), $this->team()]);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function createEntry(): void
    {
        Gate::authorize('manage', [$this->catalog->modelClass(), $this->team()]);

        $this->reset('name', 'editingId');
        $this->resetValidation();

        Flux::modal('catalog-entry')->show();
    }

    public function editEntry(int $entryId): void
    {
        $entry = $this->findEntry($entryId);

        Gate::authorize('update', $entry);

        $this->editingId = $entry->id;
        $this->name = $entry->name;
        $this->resetValidation();

        Flux::modal('catalog-entry')->show();
    }

    public function saveEntry(CreateCatalogEntry $createCatalogEntry, UpdateCatalogEntry $updateCatalogEntry): void
    {
        $team = $this->team();
        $entry = $this->editingId !== null ? $this->findEntry($this->editingId) : null;

        if ($entry !== null) {
            Gate::authorize('update', $entry);
        } else {
            Gate::authorize('manage', [$this->catalog->modelClass(), $team]);
        }

        $this->name = trim($this->name);

        $validated = $this->validate([
            'name' => CatalogRules::name($team, $this->catalog, $entry),
        ]);

        if ($entry !== null) {
            $updateCatalogEntry->handle($entry, $validated['name']);
            $message = $this->catalog->updatedEntryLabel();
        } else {
            $createCatalogEntry->handle($team, $this->catalog, $validated['name']);
            $message = $this->catalog->newEntryLabel();
        }

        $this->reset('name', 'editingId');

        Flux::modal('catalog-entry')->close();

        Flux::toast(variant: 'success', text: $message);
    }

    public function confirmDeleteEntry(int $entryId): void
    {
        $entry = $this->findEntry($entryId);

        Gate::authorize('delete', $entry);

        $this->deletingId = $entry->id;
        $this->deletingName = $entry->name;

        Flux::modal('delete-catalog-entry')->show();
    }

    public function deleteEntry(): void
    {
        $entry = $this->findEntry($this->deletingId);

        Gate::authorize('delete', $entry);

        $entry->delete();

        $this->reset('deletingId', 'deletingName');

        Flux::modal('delete-catalog-entry')->close();

        Flux::toast(variant: 'success', text: $this->catalog->deletedEntryLabel());
    }

    /**
     * @return LengthAwarePaginator<int, Model>
     */
    #[Computed]
    public function entries(): LengthAwarePaginator
    {
        return $this->team()->{$this->catalog->relationName()}()
            ->when($this->search !== '', fn ($query) => $query->whereLike('name', "%{$this->search}%"))
            ->orderBy('name')
            ->paginate(in_array($this->perPage, self::PER_PAGE_OPTIONS, true) ? $this->perPage : self::PER_PAGE_OPTIONS[0]);
    }

    #[Computed]
    public function canManage(): bool
    {
        return Auth::user()->toTeamPermissions($this->team())->canManageCatalogs;
    }

    /**
     * Find one of the current team's entries of this catalog, with the team already set for the policy checks.
     */
    private function findEntry(?int $entryId): Model
    {
        $team = $this->team();

        return $team->{$this->catalog->relationName()}()->findOrFail($entryId)->setRelation('team', $team);
    }

    private function team(): Team
    {
        return Auth::user()->currentTeam;
    }

    public function render()
    {
        return $this->view()->title($this->catalog->label());
    }
}; ?>

<section class="w-full">
    @include('partials.catalogs-heading')

    <x-pages::catalogs.layout :heading="$catalog->label()" :subheading="$catalog->description()">
        @if ($this->canManage)
            <x-slot:actions>
                <flux:button variant="primary" icon="plus" wire:click="createEntry" class="shrink-0" data-test="catalog-entry-new-button">
                    {{ $catalog->newEntryTitle() }}
                </flux:button>
            </x-slot:actions>
        @endif

        <flux:input
            wire:model.live.debounce.300ms="search"
            icon="magnifying-glass"
            :placeholder="__('Search by name...')"
            clearable
            data-test="catalog-entries-search-input"
        />

        <flux:card class="mt-4 scroll-mt-6 px-4 pt-0 pb-4 [--flux-bleed:1rem]" id="catalog-entries-table">
            @if ($this->entries->isNotEmpty())
                <flux:table bleed>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Name') }}</flux:table.column>
                        @if ($this->canManage)
                            <flux:table.column></flux:table.column>
                        @endif
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($this->entries as $entry)
                            <flux:table.row :key="$entry->id" :class="$this->canManage ? 'relative cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-800' : null" data-test="catalog-entry-row">
                                <flux:table.cell class="text-[15px] text-zinc-900 dark:text-white">
                                    @if ($this->canManage)
                                        <button
                                            type="button"
                                            wire:click="editEntry({{ $entry->id }})"
                                            class="absolute inset-0 cursor-pointer"
                                            aria-label="{{ __('Edit :name', ['name' => $entry->name]) }}"
                                            data-test="catalog-entry-edit-row"
                                        ></button>
                                    @endif

                                    {{ $entry->name }}
                                </flux:table.cell>

                                @if ($this->canManage)
                                    <flux:table.cell align="end" class="relative z-10">
                                        <flux:dropdown position="bottom" align="end">
                                            <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" icon:variant="outline" :aria-label="__('More actions')" data-test="catalog-entry-actions-trigger" />
                                            <flux:menu>
                                                <flux:menu.item icon="pencil" icon:variant="outline" wire:click="editEntry({{ $entry->id }})" data-test="catalog-entry-edit-menu-item">
                                                    {{ __('Edit') }}
                                                </flux:menu.item>
                                                <flux:menu.item variant="danger" icon="trash" icon:variant="outline" wire:click="confirmDeleteEntry({{ $entry->id }})" data-test="catalog-entry-delete-menu-item">
                                                    {{ __('Delete') }}
                                                </flux:menu.item>
                                            </flux:menu>
                                        </flux:dropdown>
                                    </flux:table.cell>
                                @endif
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>

                <div class="@container flex flex-wrap items-center justify-center gap-3 border-t border-zinc-100 pt-3 dark:border-zinc-700">
                    <flux:pagination :paginator="$this->entries" scroll-to="#catalog-entries-table" class="contents! @container-normal! *:order-2 [&>:first-child]:order-none [&>:first-child]:font-normal @max-[40rem]:[&>:first-child]:w-full @max-[40rem]:[&>:first-child]:text-center" />

                    <div class="order-1 flex items-center gap-2 @[40rem]:ms-auto">
                        <flux:text class="whitespace-nowrap text-xs">{{ __('Per page') }}</flux:text>
                        <flux:select wire:model.live="perPage" size="xs" data-test="catalog-entries-per-page-select">
                            @foreach ($this::PER_PAGE_OPTIONS as $option)
                                <flux:select.option :value="$option">{{ $option }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                </div>
            @else
                <flux:text class="pt-8 pb-4 text-center text-zinc-500 dark:text-zinc-400">
                    @if ($this->search !== '')
                        {{ __('No entries match your search.') }}
                    @else
                        {{ __('No entries have been registered yet.') }}
                    @endif
                </flux:text>
            @endif
        </flux:card>
    </x-pages::catalogs.layout>

    <flux:modal name="catalog-entry" :show="$errors->isNotEmpty()" focusable class="w-full max-w-lg">
        <form wire:submit="saveEntry" class="space-y-6">
            <flux:heading size="lg">{{ $editingId !== null ? $catalog->editEntryTitle() : $catalog->newEntryTitle() }}</flux:heading>

            <flux:input wire:model="name" :label="__('Name')" required autofocus data-test="catalog-entry-name-input" />

            <div class="flex justify-end space-x-2 rtl:space-x-reverse">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" type="submit" wire:loading.attr="disabled" data-test="catalog-entry-save-button">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="delete-catalog-entry" focusable class="max-w-lg">
        <form wire:submit="deleteEntry" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $catalog->deleteEntryTitle() }}</flux:heading>
                <flux:subheading>
                    {{ __('Are you sure you want to delete :name? It will no longer be offered in new appointments, but will remain on the records that already use it.', ['name' => $deletingName]) }}
                </flux:subheading>
            </div>
            <div class="flex justify-end space-x-2 rtl:space-x-reverse">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" type="submit" wire:loading.attr="disabled" data-test="catalog-entry-delete-confirm">
                    {{ __('Delete') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</section>
