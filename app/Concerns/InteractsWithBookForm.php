<?php

namespace App\Concerns;

use App\Actions\Catalogs\CreateCatalogEntry;
use App\Actions\Library\DownloadBookCover;
use App\Actions\Library\LookUpBookByIsbn;
use App\Actions\Library\StoreBookCover;
use App\Enums\AuthorRole;
use App\Enums\LibraryCatalog;
use App\Models\Book;
use App\Models\Team;
use App\Rules\CatalogRules;
use App\Rules\Isbn;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Json;
use Livewire\Attributes\Locked;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * The fields of the book form shared by the create and edit pages, the ISBN lookup that fills them, the
 * cover they choose, and the pickers of the library catalogs. The page using it also uses WithFileUploads.
 */
trait InteractsWithBookForm
{
    use ComparesNames;

    /**
     * How many ISBN lookups a user may make per minute, as each one calls the public catalogs.
     */
    private const int ISBN_LOOKUPS_PER_MINUTE = 20;

    public string $isbn = '';

    public string $title = '';

    public string $subtitle = '';

    public string $edition = '';

    public string $publishedYear = '';

    public string $pageCount = '';

    public string $synopsis = '';

    public string $publisherId = '';

    public string $categoryId = '';

    public string $collectionId = '';

    public string $collectionVolume = '';

    /**
     * @var list<string>
     */
    public array $incarnateAuthorIds = [];

    /**
     * @var list<string>
     */
    public array $spiritualAuthorIds = [];

    public ?TemporaryUploadedFile $cover = null;

    /**
     * The cover found by the last ISBN lookup. Locked, so the browser can never choose what the server downloads.
     */
    #[Locked]
    public ?string $foundCoverUrl = null;

    /**
     * Whether the book's current cover is to be removed when saving.
     */
    #[Locked]
    public bool $removeCover = false;

    /**
     * Author names found by the ISBN lookup that are not in the library yet, waiting to be given a role.
     *
     * @var list<string>
     */
    #[Locked]
    public array $suggestedAuthorNames = [];

    /**
     * The suggested author names a catalog marked as the spirit who dictated the book.
     *
     * @var list<string>
     */
    #[Locked]
    public array $suggestedSpiritualAuthorNames = [];

    /**
     * The publisher name found by the ISBN lookup when it is not in the library yet.
     */
    #[Locked]
    public ?string $suggestedPublisherName = null;

    /**
     * The collection name found by the ISBN lookup when it is not in the library yet.
     */
    #[Locked]
    public ?string $suggestedCollectionName = null;

    /**
     * Fill in the blank fields with what the free public catalogs know about the typed ISBN.
     */
    public function lookUpIsbn(LookUpBookByIsbn $lookUpBookByIsbn): void
    {
        Gate::authorize('create', [Book::class, $this->team()]);

        $this->resetValidation('isbn');

        $validated = Validator::make(['isbn' => $this->isbn], ['isbn' => ['required', new Isbn]], [], ['isbn' => 'ISBN'])->validate();

        if (! RateLimiter::attempt('isbn-lookup:'.Auth::id(), self::ISBN_LOOKUPS_PER_MINUTE, fn () => true)) {
            $this->addError('isbn', __('Too many lookups. Please wait a minute and try again.'));

            return;
        }

        $lookup = $lookUpBookByIsbn->handle($validated['isbn']);

        if ($lookup === null) {
            Flux::toast(variant: 'warning', text: __('No public catalog knows this ISBN. Please fill in the book by hand.'));

            return;
        }

        $this->isbn = (string) Isbn::toIsbn13($validated['isbn']);
        $this->title = $this->title !== '' ? $this->title : (string) $lookup->title;
        $this->subtitle = $this->subtitle !== '' ? $this->subtitle : (string) $lookup->subtitle;
        $this->edition = $this->edition !== '' || $lookup->editionNumber === null ? $this->edition : $lookup->editionNumber.'ª';
        $this->publishedYear = $this->publishedYear !== '' ? $this->publishedYear : (string) $lookup->publishedYear;
        $this->pageCount = $this->pageCount !== '' ? $this->pageCount : (string) $lookup->pageCount;
        $this->synopsis = $this->synopsis !== '' ? $this->synopsis : (string) $lookup->synopsis;

        $this->foundCoverUrl = $this->hasCover() ? null : $lookup->coverUrl;

        $this->suggestAuthors($lookup->authorNames, $lookup->spiritualAuthorNames);
        $this->suggestedPublisherName = $this->selectOrSuggestEntry(LibraryCatalog::Publisher, 'publisherId', $lookup->publisherName);
        $this->suggestedCollectionName = $this->selectOrSuggestEntry(LibraryCatalog::Collection, 'collectionId', $lookup->collectionName);

        Flux::toast(variant: 'success', text: __('Book found. Please review the details before saving.'));
    }

    /**
     * Register one of the author names found by the lookup in the library and select it in the given role.
     */
    public function addSuggestedAuthor(CreateCatalogEntry $createCatalogEntry, int $index, string $role): void
    {
        Gate::authorize('create', [LibraryCatalog::Author->modelClass(), $this->team()]);

        $name = $this->suggestedAuthorNames[$index] ?? null;
        $role = AuthorRole::tryFrom($role);

        if ($name === null || $role === null) {
            return;
        }

        $this->selectAuthor((string) $this->findOrCreateEntry($createCatalogEntry, LibraryCatalog::Author, $name)->getKey(), $role);

        $this->dismissSuggestedAuthor($index);
    }

    /**
     * Forget one of the author names found by the lookup.
     */
    public function dismissSuggestedAuthor(int $index): void
    {
        unset($this->suggestedAuthorNames[$index]);

        $this->suggestedAuthorNames = array_values($this->suggestedAuthorNames);
    }

    /**
     * Register the publisher found by the lookup in the library and select it.
     */
    public function addSuggestedPublisher(CreateCatalogEntry $createCatalogEntry): void
    {
        Gate::authorize('create', [LibraryCatalog::Publisher->modelClass(), $this->team()]);

        if ($this->suggestedPublisherName === null) {
            return;
        }

        $this->publisherId = (string) $this->findOrCreateEntry($createCatalogEntry, LibraryCatalog::Publisher, $this->suggestedPublisherName)->getKey();

        $this->dismissSuggestedPublisher();
    }

    /**
     * Forget the publisher found by the lookup.
     */
    public function dismissSuggestedPublisher(): void
    {
        $this->suggestedPublisherName = null;
    }

    /**
     * Register the collection found by the lookup in the library and select it.
     */
    public function addSuggestedCollection(CreateCatalogEntry $createCatalogEntry): void
    {
        Gate::authorize('create', [LibraryCatalog::Collection->modelClass(), $this->team()]);

        if ($this->suggestedCollectionName === null) {
            return;
        }

        $this->collectionId = (string) $this->findOrCreateEntry($createCatalogEntry, LibraryCatalog::Collection, $this->suggestedCollectionName)->getKey();

        $this->dismissSuggestedCollection();
    }

    /**
     * Forget the collection found by the lookup.
     */
    public function dismissSuggestedCollection(): void
    {
        $this->suggestedCollectionName = null;
    }

    /**
     * Drop the uploaded or found cover, and the book's current cover when there is no new one.
     */
    public function removeCoverImage(): void
    {
        if ($this->cover === null && $this->foundCoverUrl === null) {
            $this->removeCover = true;
        }

        $this->reset('cover', 'foundCoverUrl');
    }

    /**
     * Create an entry in one of the library catalogs from a picker and select it right away.
     *
     * The index is the role the new author is selected in, as each of the two author pickers passes its own.
     *
     * @return array{id: string, name: string}
     */
    public function createCatalogEntry(CreateCatalogEntry $createCatalogEntry, LibraryCatalog $catalog, string $name, ?string $index = null): array
    {
        $team = $this->team();

        Gate::authorize('create', [$catalog->modelClass(), $team]);

        $errorKey = 'new_'.$catalog->value;

        $validated = Validator::make([$errorKey => trim($name)], [$errorKey => CatalogRules::name($team, $catalog)], [], [$errorKey => __('name')])->validate();

        $entry = $createCatalogEntry->handle($team, $catalog, $validated[$errorKey]);
        $entryId = (string) $entry->getKey();

        match ($catalog) {
            LibraryCatalog::Author => $this->selectAuthor($entryId, AuthorRole::tryFrom((string) $index) ?? AuthorRole::Incarnate),
            LibraryCatalog::Publisher => $this->publisherId = $entryId,
            LibraryCatalog::Category => $this->categoryId = $entryId,
            LibraryCatalog::Collection => $this->collectionId = $entryId,
        };

        $this->resetValidation($errorKey);

        Flux::toast(variant: 'success', text: $catalog->newEntryLabel());

        return ['id' => $entryId, 'name' => $entry->getAttribute('name')];
    }

    /**
     * Get every entry the form's pickers offer, loaded once in the background right after the page opens.
     *
     * Keyed by the pickers' cache key, each library catalog's value; both author pickers share one list.
     *
     * @return array<string, list<array{id: string, name: string}>>
     */
    #[Json]
    public function pickerOptions(): array
    {
        Gate::authorize('viewAny', [Book::class, $this->team()]);

        $options = [];

        foreach (LibraryCatalog::cases() as $catalog) {
            $options[$catalog->value] = $this->catalogEntries($catalog)->map(fn (Model $entry): array => [
                'id' => (string) $entry->getKey(),
                'name' => $entry->getAttribute('name'),
            ])->values()->all();
        }

        return $options;
    }

    /**
     * Get the selected entries of each library catalog, keyed by its value, which name the pickers'
     * selections; the pickers load the full lists themselves through pickerOptions().
     *
     * @return array<string, Collection<int, Model>>
     */
    #[Computed]
    public function selectedCatalogEntries(): array
    {
        $selectedIds = [
            LibraryCatalog::Author->value => [...$this->incarnateAuthorIds, ...$this->spiritualAuthorIds],
            LibraryCatalog::Publisher->value => [$this->publisherId],
            LibraryCatalog::Category->value => [$this->categoryId],
            LibraryCatalog::Collection->value => [$this->collectionId],
        ];

        return collect(LibraryCatalog::cases())->mapWithKeys(function (LibraryCatalog $catalog) use ($selectedIds) {
            $ids = array_filter($selectedIds[$catalog->value], fn (string $id) => $id !== '');

            return [$catalog->value => $ids === []
                ? new Collection
                : $this->team()->{$catalog->relationName()}()->whereKey($ids)->get(['id', 'name'])];
        })->all();
    }

    /**
     * Get the URL of the cover the book will have once saved, if any: an uploaded one first, then the one
     * found by the lookup, then the book's current cover.
     */
    #[Computed]
    public function coverPreviewUrl(): ?string
    {
        return match (true) {
            $this->cover?->isPreviewable() === true => $this->cover->temporaryUrl(),
            $this->foundCoverUrl !== null => $this->foundCoverUrl,
            default => $this->currentCoverUrl(),
        };
    }

    /**
     * Map validated form data to the attribute shape expected by the create/update actions.
     *
     * @param  array<string, mixed>  $validated
     * @return array{title: string, subtitle: ?string, isbn: ?string, edition: ?string, published_year: int|string|null,
     *              page_count: int|string|null, synopsis: ?string, publisher_id: int|string|null, book_category_id: int|string|null,
     *              book_collection_id: int|string|null, collection_volume: int|string|null}
     */
    protected function bookAttributes(array $validated): array
    {
        return [
            'title' => $validated['title'],
            'subtitle' => $validated['subtitle'],
            'isbn' => $validated['isbn'],
            'edition' => $validated['edition'],
            'published_year' => $validated['publishedYear'],
            'page_count' => $validated['pageCount'],
            'synopsis' => $validated['synopsis'],
            'publisher_id' => $validated['publisherId'],
            'book_category_id' => $validated['categoryId'],
            'book_collection_id' => $validated['collectionId'],
            'collection_volume' => $validated['collectionVolume'],
        ];
    }

    /**
     * Get the selected authors of each role, in the shape expected by the create/update actions.
     *
     * @param  array<string, mixed>  $validated
     * @return array{incarnate: array<int, int|string>, spiritual: array<int, int|string>}
     */
    protected function bookAuthorIds(array $validated): array
    {
        return [
            AuthorRole::Incarnate->value => $validated['incarnateAuthorIds'] ?? [],
            AuthorRole::Spiritual->value => $validated['spiritualAuthorIds'] ?? [],
        ];
    }

    /**
     * Store the cover chosen for the book, the uploaded one first, otherwise the one found by the lookup,
     * returning its path, or null when there is none or the found one could not be downloaded.
     */
    protected function storeChosenCover(StoreBookCover $storeBookCover, DownloadBookCover $downloadBookCover): ?string
    {
        if ($this->cover !== null) {
            return $storeBookCover->handle($this->cover);
        }

        if ($this->foundCoverUrl === null) {
            return null;
        }

        $path = $downloadBookCover->handle($this->foundCoverUrl);

        if ($path === null) {
            Flux::toast(variant: 'warning', text: __('The cover found could not be downloaded. You can upload one by editing the book.'));
        }

        return $path;
    }

    /**
     * Get the URL of the cover the book already has and keeps, if any. A new book has none.
     */
    protected function currentCoverUrl(): ?string
    {
        return null;
    }

    /**
     * Determine whether the book keeps a cover or gets an uploaded one, so a found one is not offered.
     */
    private function hasCover(): bool
    {
        return $this->cover !== null || $this->currentCoverUrl() !== null;
    }

    /**
     * Select the existing authors among the found names, as spiritual authors when a catalog marked them as
     * spirits and otherwise in the role they usually have in the library, and offer the others as suggestions.
     *
     * @param  list<string>  $names
     * @param  list<string>  $spiritualNames
     */
    private function suggestAuthors(array $names, array $spiritualNames): void
    {
        $authors = $this->catalogEntries(LibraryCatalog::Author);
        $foundIsSpirit = [];

        $this->suggestedAuthorNames = [];
        $this->suggestedSpiritualAuthorNames = $spiritualNames;

        foreach ($names as $name) {
            $author = $this->findEntryByName($authors, $name);

            if ($author === null) {
                $this->suggestedAuthorNames[] = $name;
            } elseif (! in_array((string) $author->getKey(), [...$this->incarnateAuthorIds, ...$this->spiritualAuthorIds], true)) {
                $foundIsSpirit[$author->getKey()] = in_array($name, $spiritualNames, true);
            }
        }

        $usualRoles = $this->usualRolesOf(array_keys($foundIsSpirit));

        foreach ($foundIsSpirit as $authorId => $isSpirit) {
            $this->selectAuthor((string) $authorId, $isSpirit ? AuthorRole::Spiritual : ($usualRoles[$authorId] ?? AuthorRole::Incarnate));
        }
    }

    /**
     * Select the existing entry of the found name in a single-choice catalog when none is selected yet, returning
     * the name to offer as a suggestion when the library does not have it.
     *
     * @param  'publisherId'|'collectionId'  $selectedIdProperty
     */
    private function selectOrSuggestEntry(LibraryCatalog $catalog, string $selectedIdProperty, ?string $name): ?string
    {
        if ($name === null || $this->{$selectedIdProperty} !== '') {
            return null;
        }

        $entry = $this->findEntryByName($this->catalogEntries($catalog), $name);

        if ($entry === null) {
            return $name;
        }

        $this->{$selectedIdProperty} = (string) $entry->getKey();

        return null;
    }

    /**
     * Select the author in one role, removing it from the other.
     */
    private function selectAuthor(string $authorId, AuthorRole $role): void
    {
        $this->incarnateAuthorIds = array_values(array_diff($this->incarnateAuthorIds, [$authorId]));
        $this->spiritualAuthorIds = array_values(array_diff($this->spiritualAuthorIds, [$authorId]));

        if ($role === AuthorRole::Spiritual) {
            $this->spiritualAuthorIds[] = $authorId;
        } else {
            $this->incarnateAuthorIds[] = $authorId;
        }
    }

    /**
     * Get the role each of the authors has had most often on the library's books, keyed by author id.
     *
     * @param  list<int>  $authorIds
     * @return array<int, AuthorRole>
     */
    private function usualRolesOf(array $authorIds): array
    {
        if ($authorIds === []) {
            return [];
        }

        return DB::table('author_book')
            ->whereIn('author_id', $authorIds)
            ->select('author_id', 'role', DB::raw('count(*) as uses'))
            ->groupBy('author_id', 'role')
            ->orderByDesc('uses')
            ->get()
            ->unique('author_id')
            ->mapWithKeys(fn (object $row) => [(int) $row->author_id => AuthorRole::from($row->role)])
            ->all();
    }

    /**
     * Find the entry of a library catalog with the given name, or register it.
     */
    private function findOrCreateEntry(CreateCatalogEntry $createCatalogEntry, LibraryCatalog $catalog, string $name): Model
    {
        return $this->findEntryByName($this->catalogEntries($catalog), $name)
            ?? $createCatalogEntry->handle($this->team(), $catalog, Str::limit($name, 150, ''));
    }

    /**
     * Find an entry by its name, ignoring case and accents, as catalogs spell names differently.
     *
     * @param  Collection<int, Model>  $entries
     */
    private function findEntryByName(Collection $entries, string $name): ?Model
    {
        $key = $this->nameKey($name);

        return $entries->first(fn (Model $entry) => $this->nameKey($entry->getAttribute('name')) === $key);
    }

    /**
     * @return Collection<int, Model>
     */
    private function catalogEntries(LibraryCatalog $catalog): Collection
    {
        return $this->team()->{$catalog->relationName()}()->orderBy('name')->get(['id', 'name']);
    }

    /**
     * Get the team whose library the form belongs to.
     */
    protected function team(): Team
    {
        return Auth::user()->currentTeam;
    }
}
