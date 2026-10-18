<?php

use App\Actions\Library\AddBookCopies;
use App\Actions\Library\DeleteBook;
use App\Actions\Library\DeleteBookCopy;
use App\Actions\Library\UpdateBookCopy;
use App\Enums\AuthorRole;
use App\Enums\BookCopyStatus;
use App\Enums\BookLoanStatus;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\BookLoan;
use App\Rules\BookCopyRules;
use App\Rules\BookRules;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Renderless;
use Livewire\Component;

new class extends Component
{
    /**
     * How many past loans the history shows at first, and how many more each "load more" adds.
     */
    public const int LOANS_PER_PAGE = 10;

    public Book $book;

    public string $newCopiesCount = '1';

    #[Locked]
    public ?int $editingCopyId = null;

    public string $copyCode = '';

    public string $copyStatus = '';

    public string $copyAcquiredOn = '';

    public string $copyNotes = '';

    #[Locked]
    public ?int $deletingCopyId = null;

    public int $loansLimit = self::LOANS_PER_PAGE;

    public function mount(Book $book): void
    {
        Gate::authorize('view', $book);

        $this->book = $book;
    }

    public function addCopies(AddBookCopies $addBookCopies): void
    {
        Gate::authorize('create', [BookCopy::class, $this->book->team]);

        $validated = $this->validate(['newCopiesCount' => BookRules::copiesCount()], [], ['newCopiesCount' => __('number of copies')]);

        $copies = $addBookCopies->handle($this->book, (int) $validated['newCopiesCount']);

        $this->reset('newCopiesCount');
        unset($this->copies);

        Flux::modal('add-book-copies')->close();

        Flux::toast(variant: 'success', text: trans_choice('Copy :codes added.|Copies :codes added.', $copies->count(), [
            'codes' => $copies->pluck('formatted_code')->join(', '),
        ]));
    }

    public function editCopy(int $copyId): void
    {
        $copy = $this->findCopy($copyId);

        Gate::authorize('update', $copy);

        $this->editingCopyId = $copy->id;
        $this->copyCode = (string) $copy->code;
        $this->copyStatus = $copy->status->value;
        $this->copyAcquiredOn = $copy->acquired_on?->format('Y-m-d') ?? '';
        $this->copyNotes = $copy->notes ?? '';
        $this->resetValidation();

        Flux::modal('edit-book-copy')->show();
    }

    public function saveCopy(UpdateBookCopy $updateBookCopy): void
    {
        $copy = $this->findCopy($this->editingCopyId);

        Gate::authorize('update', $copy);

        $validated = $this->validate(BookCopyRules::all($this->book->team, $copy), [], [
            'copyCode' => __('code'),
            'copyStatus' => __('status'),
            'copyAcquiredOn' => __('acquisition date'),
            'copyNotes' => __('notes'),
        ]);

        $updateBookCopy->handle($copy, [
            'code' => $validated['copyCode'],
            'status' => $validated['copyStatus'],
            'acquired_on' => $validated['copyAcquiredOn'],
            'notes' => $validated['copyNotes'],
        ]);

        $this->reset('editingCopyId', 'copyCode', 'copyStatus', 'copyAcquiredOn', 'copyNotes');
        unset($this->copies);

        Flux::modal('edit-book-copy')->close();

        Flux::toast(variant: 'success', text: __('Copy updated.'));
    }

    #[Renderless]
    public function confirmDeleteCopy(int $copyId): void
    {
        $copy = $this->findCopy($copyId);

        Gate::authorize('delete', $copy);

        $this->deletingCopyId = $copy->id;

        Flux::modal('delete-book-copy')->show();
    }

    public function deleteCopy(DeleteBookCopy $deleteBookCopy): void
    {
        $copy = $this->findCopy($this->deletingCopyId);

        Gate::authorize('delete', $copy);

        try {
            $deleteBookCopy->handle($copy);
        } catch (ValidationException $exception) {
            Flux::modal('delete-book-copy')->close();
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        $this->reset('deletingCopyId');
        unset($this->copies);

        Flux::modal('delete-book-copy')->close();

        Flux::toast(variant: 'success', text: __('Copy deleted.'));
    }

    public function deleteBook(DeleteBook $deleteBook): void
    {
        Gate::authorize('delete', $this->book);

        try {
            $deleteBook->handle($this->book);
        } catch (ValidationException $exception) {
            Flux::modal('delete-book')->close();
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: __('Book deleted.'));

        $this->redirectRoute('library.books.index', navigate: true);
    }

    public function loadMoreLoans(): void
    {
        $this->loansLimit += self::LOANS_PER_PAGE;
    }

    /**
     * @return Collection<int, BookCopy>
     */
    #[Computed]
    public function copies(): Collection
    {
        return $this->book->copies()
            ->with('openLoan.assistedPerson')
            ->orderBy('code')
            ->get();
    }

    /**
     * @return Collection<int, BookLoan>
     */
    #[Computed]
    public function loans(): Collection
    {
        return $this->book->loans()
            ->with(['assistedPerson', 'copy'])
            ->orderByDesc('loaned_on')
            ->orderByDesc('book_loans.id')
            ->limit($this->loansLimit + 1)
            ->get();
    }

    #[Computed]
    public function canManage(): bool
    {
        return Auth::user()->toTeamPermissions($this->book->team)->canManageCatalogs;
    }

    /**
     * Find one of the book's copies, with the team already set for the policy checks.
     */
    private function findCopy(?int $copyId): BookCopy
    {
        return $this->book->copies()->findOrFail($copyId)->setRelation('team', $this->book->team);
    }

    public function render()
    {
        $this->book->loadMissing(['authors', 'publisher', 'category', 'collection']);

        return $this->view()->title($this->book->title);
    }
}; ?>


<section class="w-full">
    <x-page-header :heading="$book->title" :subheading="$book->subtitle" :back-href="route('library.books.index')" :back-label="__('Back to books')" stack-until="lg">
        @if ($this->canManage)
            <div class="flex items-center gap-2">
                <flux:button icon="pencil" icon:variant="outline" :href="route('library.books.edit', ['book' => $book])" wire:navigate data-test="book-edit-button">
                    {{ __('Edit') }}
                </flux:button>

                <flux:modal.trigger name="delete-book">
                    <flux:button variant="danger" icon="trash" icon:variant="outline" data-test="book-delete-button">{{ __('Delete') }}</flux:button>
                </flux:modal.trigger>
            </div>
        @endif
    </x-page-header>

    <div class="grid gap-8 lg:grid-cols-[12rem_1fr]">
        <div class="flex aspect-2/3 w-full max-w-48 items-center justify-center overflow-hidden rounded-lg border border-zinc-200 bg-zinc-50 dark:border-white/10 dark:bg-white/5">
            @if ($book->cover_url)
                <img src="{{ $book->cover_url }}" alt="{{ __('Cover of :name', ['name' => $book->title]) }}" class="size-full object-cover" data-test="book-cover" />
            @else
                <flux:icon name="book-open" class="size-12 text-zinc-300 dark:text-zinc-600" />
            @endif
        </div>

        <div class="min-w-0 space-y-8">
            <dl class="grid gap-x-6 gap-y-4 sm:grid-cols-2">
                <div>
                    <dt class="text-sm text-zinc-500 dark:text-zinc-400">{{ AuthorRole::Incarnate->pluralLabel() }}</dt>
                    <dd class="text-zinc-900 dark:text-white" data-test="book-incarnate-authors">{{ $book->authorsWithRole(AuthorRole::Incarnate)->pluck('name')->join(', ') ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-zinc-500 dark:text-zinc-400">{{ AuthorRole::Spiritual->pluralLabel() }}</dt>
                    <dd class="text-zinc-900 dark:text-white" data-test="book-spiritual-authors">{{ $book->authorsWithRole(AuthorRole::Spiritual)->pluck('name')->join(', ') ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Publisher') }}</dt>
                    <dd class="text-zinc-900 dark:text-white">{{ $book->publisher?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Category') }}</dt>
                    <dd class="text-zinc-900 dark:text-white">{{ $book->category?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Collection') }}</dt>
                    <dd class="text-zinc-900 dark:text-white">
                        @if ($book->collection)
                            {{ $book->collection->name }}@if ($book->collection_volume), {{ __('vol. :number', ['number' => $book->collection_volume]) }}@endif
                        @else
                            —
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('ISBN') }}</dt>
                    <dd class="text-zinc-900 dark:text-white">{{ $book->formatted_isbn ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Edition') }}</dt>
                    <dd class="text-zinc-900 dark:text-white">{{ collect([$book->edition, $book->published_year])->filter()->join(' · ') ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Pages') }}</dt>
                    <dd class="text-zinc-900 dark:text-white">{{ $book->page_count ?? '—' }}</dd>
                </div>
            </dl>

            @if ($book->synopsis)
                <div>
                    <flux:heading>{{ __('Synopsis') }}</flux:heading>
                    <flux:text class="mt-2 whitespace-pre-line">{{ $book->synopsis }}</flux:text>
                </div>
            @endif

            <div>
                <div class="mb-3 flex items-center justify-between gap-4">
                    <flux:heading size="lg">{{ __('Copies') }}</flux:heading>

                    <flux:modal.trigger name="add-book-copies">
                        <flux:button size="sm" icon="plus" icon:variant="outline" data-test="book-add-copies-button">{{ __('Add copies') }}</flux:button>
                    </flux:modal.trigger>
                </div>

                <flux:card class="px-4 [--flux-bleed:1rem]">
                    @if ($this->copies->isNotEmpty())
                        <flux:table bleed>
                            <flux:table.columns>
                                <flux:table.column>{{ __('Code') }}</flux:table.column>
                                <flux:table.column>{{ __('Status') }}</flux:table.column>
                                <flux:table.column>{{ __('With') }}</flux:table.column>
                                <flux:table.column></flux:table.column>
                            </flux:table.columns>

                            <flux:table.rows>
                                @foreach ($this->copies as $copy)
                                    <flux:table.row :key="$copy->id" data-test="book-copy-row">
                                        <flux:table.cell class="font-mono text-[15px] text-zinc-900 dark:text-white">{{ $copy->formatted_code }}</flux:table.cell>

                                        <flux:table.cell>
                                            @if ($copy->openLoan)
                                                <flux:badge size="sm" :color="$copy->openLoan->status->color()">{{ $copy->openLoan->status === BookLoanStatus::Overdue ? __('Overdue') : __('Lent') }}</flux:badge>
                                            @elseif ($copy->status === BookCopyStatus::Active)
                                                <flux:badge size="sm" color="green">{{ __('Available to lend') }}</flux:badge>
                                            @else
                                                <flux:badge size="sm" :color="$copy->status->color()">{{ $copy->status->label() }}</flux:badge>
                                            @endif
                                        </flux:table.cell>

                                        <flux:table.cell class="whitespace-normal">
                                            @if ($copy->openLoan)
                                                <div class="text-zinc-900 dark:text-white">{{ $copy->openLoan->assistedPerson->name }}</div>
                                                <div>{{ __('Due on :date', ['date' => $copy->openLoan->due_on->format('d/m/Y')]) }}</div>
                                            @else
                                                {{ $copy->notes ?? '—' }}
                                            @endif
                                        </flux:table.cell>

                                        <flux:table.cell align="end">
                                            <div class="flex items-center justify-end gap-1">
                                                @if ($copy->isAvailable())
                                                    <flux:button size="sm" variant="ghost" icon="arrow-up-right" icon:variant="outline" :href="route('library.loans.create', ['book_copy' => $copy->id])" wire:navigate data-test="book-copy-lend-button">
                                                        {{ __('Lend') }}
                                                    </flux:button>
                                                @endif

                                                @if ($this->canManage)
                                                    <flux:dropdown position="bottom" align="end">
                                                        <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" icon:variant="outline" :aria-label="__('More actions')" data-test="book-copy-actions-trigger" />
                                                        <flux:menu>
                                                            <flux:menu.item icon="pencil" icon:variant="outline" wire:click="editCopy({{ $copy->id }})" data-test="book-copy-edit-menu-item">{{ __('Edit') }}</flux:menu.item>
                                                            <flux:menu.item variant="danger" icon="trash" icon:variant="outline" wire:click="confirmDeleteCopy({{ $copy->id }})" data-test="book-copy-delete-menu-item">{{ __('Delete') }}</flux:menu.item>
                                                        </flux:menu>
                                                    </flux:dropdown>
                                                @endif
                                            </div>
                                        </flux:table.cell>
                                    </flux:table.row>
                                @endforeach
                            </flux:table.rows>
                        </flux:table>
                    @else
                        <flux:text class="py-6 text-center text-zinc-500 dark:text-zinc-400">{{ __('This book has no copies yet.') }}</flux:text>
                    @endif
                </flux:card>
            </div>

            <div>
                <flux:heading size="lg" class="mb-3">{{ __('Loan history') }}</flux:heading>

                <flux:card class="px-4 [--flux-bleed:1rem]">
                    @if ($this->loans->isNotEmpty())
                        <flux:table bleed>
                            <flux:table.columns>
                                <flux:table.column>{{ __('Assisted person') }}</flux:table.column>
                                <flux:table.column>{{ __('Copy') }}</flux:table.column>
                                <flux:table.column>{{ __('Period') }}</flux:table.column>
                                <flux:table.column>{{ __('Status') }}</flux:table.column>
                            </flux:table.columns>

                            <flux:table.rows>
                                @foreach ($this->loans->take($loansLimit) as $loan)
                                    <flux:table.row :key="$loan->id" data-test="book-loan-row">
                                        <flux:table.cell class="text-zinc-900 dark:text-white">{{ $loan->assistedPerson->name }}</flux:table.cell>
                                        <flux:table.cell class="font-mono">{{ $loan->copy->formatted_code }}</flux:table.cell>
                                        <flux:table.cell>{{ $loan->loaned_on->format('d/m/Y') }} – {{ ($loan->returned_on ?? $loan->due_on)->format('d/m/Y') }}</flux:table.cell>
                                        <flux:table.cell><flux:badge size="sm" :color="$loan->status->color()">{{ $loan->status->label() }}</flux:badge></flux:table.cell>
                                    </flux:table.row>
                                @endforeach
                            </flux:table.rows>
                        </flux:table>

                        @if ($this->loans->count() > $loansLimit)
                            <div class="border-t border-zinc-800/10 py-3 text-center dark:border-white/20">
                                <flux:button size="sm" variant="ghost" wire:click="loadMoreLoans" data-test="book-loans-load-more">{{ __('Load more') }}</flux:button>
                            </div>
                        @endif
                    @else
                        <flux:text class="py-6 text-center text-zinc-500 dark:text-zinc-400">{{ __('This book has never been lent.') }}</flux:text>
                    @endif
                </flux:card>
            </div>
        </div>
    </div>

    <flux:modal name="add-book-copies" class="md:w-md">
        <form wire:submit="addCopies" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Add copies') }}</flux:heading>
                <flux:subheading>{{ __('Each copy gets the next code of the library, to be written on its label.') }}</flux:subheading>
            </div>

            <flux:input type="number" min="1" :max="BookRules::MAX_COPIES_AT_ONCE" wire:model="newCopiesCount" :label="__('Number of copies')" required data-test="book-new-copies-count-input" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" type="submit" wire:loading.attr="disabled" data-test="book-add-copies-confirm">{{ __('Add') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="edit-book-copy" flyout variant="floating" class="md:w-lg">
        <form wire:submit="saveCopy" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Edit copy') }}</flux:heading>
                <flux:subheading>{{ $book->title }}</flux:subheading>
            </div>

            <flux:input type="number" min="1" wire:model="copyCode" :label="__('Code')" :description="__('The number written on the copy\'s label.')" required data-test="book-copy-code-input" />

            <flux:select wire:model="copyStatus" :label="__('Status')" data-test="book-copy-status-select">
                @foreach (BookCopyStatus::options() as $option)
                    <flux:select.option :value="$option['value']">{{ $option['label'] }}</flux:select.option>
                @endforeach
            </flux:select>

            <x-date-input wire:model="copyAcquiredOn" :label="__('Acquired on')" :max="today()->toDateString()" data-test="book-copy-acquired-on-input" />

            <flux:input wire:model="copyNotes" :label="__('Notes')" :placeholder="__('E.g. Donated by the Silva family')" data-test="book-copy-notes-input" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" type="submit" wire:loading.attr="disabled" data-test="book-copy-save-button">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="delete-book-copy" focusable class="max-w-lg">
        <form wire:submit="deleteCopy" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete copy') }}</flux:heading>
                <flux:subheading>{{ __('The copy leaves the library, but its loans stay in the history and its code is not given to another copy.') }}</flux:subheading>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" type="submit" wire:loading.attr="disabled" data-test="book-copy-delete-confirm">{{ __('Delete') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="delete-book" focusable class="max-w-lg">
        <form wire:submit="deleteBook" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete book') }}</flux:heading>
                <flux:subheading>{{ __('Are you sure you want to delete :name and all its copies? Their loans stay in the history.', ['name' => $book->title]) }}</flux:subheading>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" type="submit" wire:loading.attr="disabled" data-test="book-delete-confirm">{{ __('Delete') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
