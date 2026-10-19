<?php

use App\Concerns\InteractsWithTableSorting;
use App\Enums\AuthorRole;
use App\Models\Book;
use App\Models\BookCategory;
use App\Models\BookCollection;
use App\Models\Team;
use App\Rules\Isbn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
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
    use InteractsWithTableSorting;
    use WithPagination;

    /**
     * @var list<int>
     */
    public const array PER_PAGE_OPTIONS = [5, 10, 25, 50, 100];

    /**
     * The columns the table can be sorted by.
     *
     * @var list<string>
     */
    public const array SORTABLE_COLUMNS = ['title', 'copies'];

    public string $search = '';

    #[Session('library-books-category')]
    public string $categoryId = '';

    #[Session('library-books-collection')]
    public string $collectionId = '';

    #[Session('library-books-available-only')]
    public bool $availableOnly = false;

    #[Session('library-books-per-page')]
    public int $perPage = 5;

    /**
     * Whether the user may edit and delete books, worked out once to show the row actions.
     */
    #[Locked]
    public bool $canManage = false;

    public function mount(): void
    {
        Gate::authorize('viewAny', [Book::class, $this->team()]);

        $this->canManage = Auth::user()->toTeamPermissions($this->team())->canManageCatalogs;
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'categoryId', 'collectionId', 'availableOnly', 'perPage'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset('categoryId', 'collectionId', 'availableOnly');
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, Book>
     */
    #[Computed]
    public function books(): LengthAwarePaginator
    {
        $search = trim($this->search);
        $isbn = Isbn::toIsbn13($search);

        return $this->team()->books()
            ->with(['publisher', 'category', 'collection', 'authors'])
            ->withCopyCounts()
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereLike('title', "%{$search}%")
                ->orWhereLike('subtitle', "%{$search}%")
                ->orWhereHas('authors', fn (Builder $authors) => $authors->whereLike('name', "%{$search}%"))
                ->when($isbn, fn (Builder $query, string $isbn) => $query->orWhere('isbn', $isbn))))
            ->when($this->categoryId !== '', fn (Builder $query) => $query->where('book_category_id', (int) $this->categoryId))
            ->when($this->collectionId !== '', fn (Builder $query) => $query->where('book_collection_id', (int) $this->collectionId))
            ->when($this->availableOnly, fn (Builder $query) => $query->available())
            ->when($this->sortBy, fn (Builder $query, string $column) => match ($column) {
                'title' => $query->orderBy('title', $this->sortDirection),
                'copies' => $query->orderBy('available_copies_count', $this->sortDirection),
            })
            ->orderBy('title')
            ->orderBy('id')
            ->paginate(in_array($this->perPage, self::PER_PAGE_OPTIONS, true) ? $this->perPage : self::PER_PAGE_OPTIONS[0]);
    }

    /**
     * @return Collection<int, BookCategory>
     */
    #[Computed]
    public function categories(): Collection
    {
        return $this->team()->bookCategories()->orderBy('name')->get(['id', 'name']);
    }

    /**
     * @return Collection<int, BookCollection>
     */
    #[Computed]
    public function collections(): Collection
    {
        return $this->team()->bookCollections()->orderBy('name')->get(['id', 'name']);
    }

    /**
     * Count the filters of the flyout in use, shown on the button that opens it.
     */
    #[Computed]
    public function activeFilterCount(): int
    {
        return (int) ($this->categoryId !== '') + (int) ($this->collectionId !== '') + (int) $this->availableOnly;
    }

    private function team(): Team
    {
        return Auth::user()->currentTeam;
    }

    public function render()
    {
        return $this->view()->title(__('Books'));
    }
}; ?>

<section class="w-full">
    <x-page-header :heading="__('Books')" :subheading="__('The library of this Spiritist Center')" :separator="false" stack-until="lg">
        <div class="flex items-center gap-3">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                :placeholder="__('Search by title, author or ISBN...')"
                clearable
                class="min-w-0 max-sm:flex-1 sm:w-72"
                data-test="books-search-input"
            />

            <flux:modal.trigger name="books-filters">
                <flux:button icon="funnel" icon:variant="outline" class="shrink-0" data-test="books-filters-button">
                    {{ __('Filters') }}
                    @if ($this->activeFilterCount > 0)
                        <flux:badge size="sm" color="blue" inset="top bottom" data-test="books-filters-count">{{ $this->activeFilterCount }}</flux:badge>
                    @endif
                </flux:button>
            </flux:modal.trigger>

            <flux:button variant="primary" icon="plus" :href="route('library.books.create')" wire:navigate class="shrink-0" data-test="books-new-button">
                {{ __('New book') }}
            </flux:button>
        </div>
    </x-page-header>

    <flux:card class="scroll-mt-6 px-4 pb-4 [--flux-bleed:1rem]" id="books-table">
        @if ($this->books->isNotEmpty())
            <flux:table bleed>
                <flux:table.columns>
                    <flux:table.column sortable :sorted="$sortBy === 'title'" :direction="$sortDirection" wire:click="sort('title')" data-test="books-sort-title">{{ __('Book') }}</flux:table.column>
                    <flux:table.column>{{ __('Authors') }}</flux:table.column>
                    <flux:table.column>{{ __('Category') }}</flux:table.column>
                    <flux:table.column align="end" sortable :sorted="$sortBy === 'copies'" :direction="$sortDirection" wire:click="sort('copies')" data-test="books-sort-copies">{{ __('Available') }}</flux:table.column>
                    @if ($this->canManage)
                        <flux:table.column></flux:table.column>
                    @endif
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->books as $book)
                        <flux:table.row :key="$book->id" class="relative cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-800" data-test="book-row">
                            <flux:table.cell class="max-w-96">
                                <a
                                    href="{{ route('library.books.show', ['book' => $book]) }}"
                                    wire:navigate
                                    class="absolute inset-0"
                                    aria-label="{{ __('View :name', ['name' => $book->title]) }}"
                                    data-test="book-show-link"
                                ></a>

                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-7 shrink-0 items-center justify-center overflow-hidden rounded border border-zinc-200 bg-zinc-50 dark:border-white/10 dark:bg-white/5">
                                        @if ($book->cover_url)
                                            <img src="{{ $book->cover_url }}" alt="" class="size-full object-cover" loading="lazy" />
                                        @else
                                            <flux:icon name="book-open" class="size-3.5 text-zinc-300 dark:text-zinc-600" />
                                        @endif
                                    </div>

                                    <div class="min-w-0">
                                        <div class="truncate font-medium text-zinc-800 dark:text-white">{{ $book->title }}</div>
                                        <div class="truncate">
                                            {{ collect([
                                                $book->publisher?->name,
                                                $book->collection_label,
                                            ])->filter()->join(' · ') ?: ($book->subtitle ?? '—') }}
                                        </div>
                                    </div>
                                </div>
                            </flux:table.cell>

                            <flux:table.cell class="whitespace-normal">
                                @php($incarnate = $book->authorsWithRole(AuthorRole::Incarnate))
                                @php($spiritual = $book->authorsWithRole(AuthorRole::Spiritual))
                                <div class="text-zinc-800 dark:text-white">{{ $incarnate->pluck('name')->join(', ') ?: '—' }}</div>
                                @if ($spiritual->isNotEmpty())
                                    <div>{{ __('By the spirit :names', ['names' => $spiritual->pluck('name')->join(', ')]) }}</div>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell class="text-zinc-800 dark:text-white">{{ $book->category?->name ?? '—' }}</flux:table.cell>

                            <flux:table.cell align="end">
                                <flux:badge size="sm" :color="$book->available_copies_count > 0 ? 'green' : 'zinc'" data-test="book-available-copies">
                                    {{ __(':available of :total', ['available' => $book->available_copies_count, 'total' => $book->active_copies_count]) }}
                                </flux:badge>
                            </flux:table.cell>

                            @if ($this->canManage)
                                <flux:table.cell align="end" class="relative z-10">
                                    <flux:dropdown position="bottom" align="end">
                                        <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" icon:variant="outline" :aria-label="__('More actions')" data-test="book-actions-trigger" />
                                        <flux:menu>
                                            <flux:menu.item as="a" :href="route('library.books.edit', ['book' => $book])" wire:navigate icon="pencil" icon:variant="outline" data-test="book-edit-menu-item">{{ __('Edit') }}</flux:menu.item>
                                            <flux:menu.item variant="danger" icon="trash" icon:variant="outline" wire:click="$dispatch('confirm-delete-book', { bookId: {{ $book->id }}, bookTitle: @js($book->title) })" data-test="book-delete-menu-item">{{ __('Delete') }}</flux:menu.item>
                                        </flux:menu>
                                    </flux:dropdown>
                                </flux:table.cell>
                            @endif
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>

            <div class="@container -mx-4 flex flex-wrap items-center justify-center gap-3 border-t border-zinc-800/10 px-4 pt-3 dark:border-white/20">
                <flux:pagination :paginator="$this->books" scroll-to="#books-table" class="contents! @container-normal! *:order-2 [&>:first-child]:order-none [&>:first-child]:font-normal @max-[40rem]:[&>:first-child]:w-full @max-[40rem]:[&>:first-child]:text-center" />

                <div class="order-1 flex items-center gap-2 @[40rem]:ms-auto">
                    <flux:text class="whitespace-nowrap text-xs">{{ __('Per page') }}</flux:text>
                    <flux:select wire:model.live="perPage" size="xs" data-test="books-per-page-select">
                        @foreach ($this::PER_PAGE_OPTIONS as $option)
                            <flux:select.option :value="$option">{{ $option }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
            </div>
        @else
            <flux:text class="pt-8 pb-4 text-center text-zinc-500 dark:text-zinc-400">
                @if ($search !== '' || $this->activeFilterCount > 0)
                    {{ __('No books match your search.') }}
                @else
                    {{ __('No books have been registered yet.') }}
                @endif
            </flux:text>
        @endif
    </flux:card>

    <flux:modal name="books-filters" flyout variant="floating" class="flex flex-col md:w-112">
        <x-flyout-layout :more-label="__('More filters')">
            <div class="pe-8">
                <flux:heading size="lg">{{ __('Filters') }}</flux:heading>
                <flux:subheading>{{ __('Refine the books shown in the list.') }}</flux:subheading>
            </div>

            <flux:radio.group wire:model.live="categoryId" variant="pills" :label="__('Category')" class="*:px-3! *:py-1.5!" data-test="books-category-filter">
                <flux:radio value="" :label="__('All categories')" />
                @foreach ($this->categories as $category)
                    <flux:radio :value="(string) $category->id" :label="$category->name" wire:key="category-filter-{{ $category->id }}" />
                @endforeach
            </flux:radio.group>

            @if ($this->collections->isNotEmpty())
                <flux:radio.group wire:model.live="collectionId" variant="pills" :label="__('Collection')" class="*:px-3! *:py-1.5!" data-test="books-collection-filter">
                    <flux:radio value="" :label="__('All collections')" />
                    @foreach ($this->collections as $collection)
                        <flux:radio :value="(string) $collection->id" :label="$collection->name" wire:key="collection-filter-{{ $collection->id }}" />
                    @endforeach
                </flux:radio.group>
            @endif

            <flux:switch wire:model.live="availableOnly" :label="__('Available only')" :description="__('Hide the books with no copy to lend now.')" data-test="books-available-filter" />

            <x-slot:footer>
                <div class="flex justify-end gap-2">
                    <flux:button variant="ghost" wire:click="clearFilters" :disabled="$this->activeFilterCount === 0" data-test="books-flyout-clear-filters">
                        {{ __('Clear filters') }}
                    </flux:button>

                    <flux:modal.close>
                        <flux:button variant="primary" data-test="books-flyout-done">
                            {{ trans_choice('Show :count book|Show :count books', $this->books->total()) }}
                        </flux:button>
                    </flux:modal.close>
                </div>
            </x-slot:footer>
        </x-flyout-layout>
    </flux:modal>

    @if ($this->canManage)
        <livewire:library.delete-book-modal @book-deleted="$refresh" />
    @endif
</section>
