<?php

use App\Actions\Library\CreateBook;
use App\Actions\Library\DownloadBookCover;
use App\Actions\Library\StoreBookCover;
use App\Actions\Library\StoreDownloadedBookCover;
use App\Concerns\InteractsWithBookForm;
use App\Models\Book;
use App\Rules\BookRules;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use InteractsWithBookForm, WithFileUploads;

    public string $copiesCount = '1';

    public function mount(): void
    {
        Gate::authorize('create', [Book::class, $this->team()]);
    }

    public function createBook(CreateBook $createBook, StoreBookCover $storeBookCover, DownloadBookCover $downloadBookCover, StoreDownloadedBookCover $storeDownloadedBookCover): void
    {
        $team = $this->team();

        Gate::authorize('create', [Book::class, $team]);

        $validated = $this->validate([
            ...BookRules::all($team),
            'copiesCount' => BookRules::copiesCount(),
        ]);

        $book = $createBook->handle(
            $team,
            $this->bookAttributes($validated),
            $this->bookAuthorIds($validated),
            (int) $validated['copiesCount'],
            $this->storeChosenCover($storeBookCover, $downloadBookCover, $storeDownloadedBookCover),
        );

        Flux::toast(variant: 'success', text: __('Book registered.'));

        $this->redirectRoute('library.books.show', ['book' => $book], navigate: true);
    }

    public function render()
    {
        return $this->view()->title(__('New book'));
    }
}; ?>

<section class="w-full">
    <x-page-header :heading="__('New book')" :subheading="__('Register a book and its copies in the library')" :back-href="route('library.books.index')" :back-label="__('Back to books')" />

    <form wire:submit="createBook" class="max-w-6xl space-y-6">
        <x-pages::library.books.form
            :selected-entries="$this->selectedCatalogEntries"
            :incarnate-author-ids="$incarnateAuthorIds"
            :spiritual-author-ids="$spiritualAuthorIds"
            :publisher-id="$publisherId"
            :category-id="$categoryId"
            :collection-id="$collectionId"
            :suggested-author-names="$suggestedAuthorNames"
            :suggested-spiritual-author-names="$suggestedSpiritualAuthorNames"
            :suggested-publisher-name="$suggestedPublisherName"
            :suggested-collection-name="$suggestedCollectionName"
            :cover-preview-url="$this->coverPreviewUrl"
            with-copies
        />

        <div class="flex justify-end gap-2">
            <flux:button variant="ghost" :href="route('library.books.index')" wire:navigate>
                {{ __('Cancel') }}
            </flux:button>

            <flux:button variant="primary" type="submit" data-test="book-save-button">
                {{ __('Register book') }}
            </flux:button>
        </div>
    </form>
</section>
