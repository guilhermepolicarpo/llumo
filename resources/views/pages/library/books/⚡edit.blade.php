<?php

use App\Actions\Library\DownloadBookCover;
use App\Actions\Library\StoreBookCover;
use App\Actions\Library\UpdateBook;
use App\Concerns\InteractsWithBookForm;
use App\Enums\AuthorRole;
use App\Models\Book;
use App\Rules\BookRules;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use InteractsWithBookForm, WithFileUploads;

    public Book $book;

    public function mount(Book $book): void
    {
        Gate::authorize('update', $book);

        $this->book = $book;

        $this->isbn = $book->isbn ?? '';
        $this->title = $book->title;
        $this->subtitle = $book->subtitle ?? '';
        $this->edition = $book->edition ?? '';
        $this->publishedYear = (string) ($book->published_year ?? '');
        $this->pageCount = (string) ($book->page_count ?? '');
        $this->synopsis = $book->synopsis ?? '';
        $this->publisherId = (string) ($book->publisher_id ?? '');
        $this->categoryId = (string) ($book->book_category_id ?? '');
        $this->collectionId = (string) ($book->book_collection_id ?? '');
        $this->collectionVolume = (string) ($book->collection_volume ?? '');

        $this->incarnateAuthorIds = $book->authorsWithRole(AuthorRole::Incarnate)->map(fn ($author) => (string) $author->id)->values()->all();
        $this->spiritualAuthorIds = $book->authorsWithRole(AuthorRole::Spiritual)->map(fn ($author) => (string) $author->id)->values()->all();
    }

    public function updateBook(UpdateBook $updateBook, StoreBookCover $storeBookCover, DownloadBookCover $downloadBookCover): void
    {
        Gate::authorize('update', $this->book);

        $validated = $this->validate(BookRules::all($this->book->team, $this->book));

        $updateBook->handle(
            $this->book,
            $this->bookAttributes($validated),
            $this->bookAuthorIds($validated),
            $this->storeChosenCover($storeBookCover, $downloadBookCover),
            $this->removeCover,
        );

        Flux::toast(variant: 'success', text: __('Book updated.'));

        $this->redirectRoute('library.books.show', ['book' => $this->book], navigate: true);
    }

    /**
     * Get the URL of the book's current cover, unless it is to be removed.
     */
    protected function currentCoverUrl(): ?string
    {
        return $this->removeCover ? null : $this->book->cover_url;
    }

    public function render()
    {
        return $this->view()->title(__('Edit :name', ['name' => $this->book->title]));
    }
}; ?>

<section class="w-full">
    <x-page-header :heading="__('Edit book')" :subheading="$book->title" :back-href="route('library.books.show', ['book' => $book])" :back-label="__('Back to book')" />

    <form wire:submit="updateBook" class="max-w-6xl space-y-6">
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
        />

        <div class="flex justify-end gap-2">
            <flux:button variant="ghost" :href="route('library.books.show', ['book' => $book])" wire:navigate>
                {{ __('Cancel') }}
            </flux:button>

            <flux:button variant="primary" type="submit" data-test="book-save-button">
                {{ __('Save changes') }}
            </flux:button>
        </div>
    </form>
</section>
