<?php

namespace App\Enums;

use App\Concerns\AddressedBySlug;
use App\Models\Author;
use App\Models\BookCategory;
use App\Models\BookCollection;
use App\Models\Publisher;
use Illuminate\Database\Eloquent\Model;

/**
 * The name-only catalogs that describe the library's books. They are kept apart from Catalog, which
 * drives the appointment record form.
 */
enum LibraryCatalog: string
{
    use AddressedBySlug;

    case Author = 'author';
    case Publisher = 'publisher';
    case Category = 'category';
    case Collection = 'collection';

    /**
     * Get the URL of the catalog's management page, so callers never build it from the enum's backing value.
     */
    public function url(): string
    {
        return route('library.catalogs', ['libraryCatalog' => $this->slug()]);
    }

    /**
     * Get the layout component of the catalog's management page, which lists the catalogs of its group.
     */
    public function layoutComponent(): string
    {
        return 'pages::library.layout';
    }

    /**
     * Get the partial that heads the catalog's management page.
     */
    public function headingView(): string
    {
        return 'partials.library-heading';
    }

    /**
     * Get the warning shown before an entry of the catalog is deleted.
     */
    public function deleteEntryWarning(string $name): string
    {
        return __('Are you sure you want to delete :name? It will no longer be offered for new books, but will remain on the books that already use it.', ['name' => $name]);
    }

    /**
     * Get the model class that stores the catalog's entries.
     *
     * @return class-string<Model>
     */
    public function modelClass(): string
    {
        return match ($this) {
            self::Author => Author::class,
            self::Publisher => Publisher::class,
            self::Category => BookCategory::class,
            self::Collection => BookCollection::class,
        };
    }

    /**
     * Get the name of the team relationship that holds the catalog's entries.
     */
    public function relationName(): string
    {
        return match ($this) {
            self::Author => 'authors',
            self::Publisher => 'publishers',
            self::Category => 'bookCategories',
            self::Collection => 'bookCollections',
        };
    }

    /**
     * Get the plural title of the catalog shown on its management page.
     */
    public function label(): string
    {
        return match ($this) {
            self::Author => __('Authors'),
            self::Publisher => __('Publishers'),
            self::Category => __('Categories'),
            self::Collection => __('Collections'),
        };
    }

    /**
     * Get the description of the catalog shown on its management page.
     */
    public function description(): string
    {
        return match ($this) {
            self::Author => __('Incarnate and spiritual authors of the books.'),
            self::Publisher => __('Publishers of the books in the library.'),
            self::Category => __('Categories used to organize the library.'),
            self::Collection => __('Collections and series the books belong to.'),
        };
    }

    /**
     * Get the example shown in the name field while creating or editing an entry.
     */
    public function namePlaceholder(): string
    {
        return match ($this) {
            self::Author => __('E.g. André Luiz'),
            self::Publisher => __('E.g. FEB'),
            self::Category => __('E.g. Spiritist novel'),
            self::Collection => __('E.g. Life in the spiritual world'),
        };
    }

    /**
     * Get the title of the button and modal used to create an entry on the management page.
     */
    public function newEntryTitle(): string
    {
        return match ($this) {
            self::Author => __('New author'),
            self::Publisher => __('New publisher'),
            self::Category => __('New category'),
            self::Collection => __('New collection'),
        };
    }

    /**
     * Get the title of the modal used to edit an entry.
     */
    public function editEntryTitle(): string
    {
        return match ($this) {
            self::Author => __('Edit author'),
            self::Publisher => __('Edit publisher'),
            self::Category => __('Edit category'),
            self::Collection => __('Edit collection'),
        };
    }

    /**
     * Get the title of the modal used to delete an entry.
     */
    public function deleteEntryTitle(): string
    {
        return match ($this) {
            self::Author => __('Delete author'),
            self::Publisher => __('Delete publisher'),
            self::Category => __('Delete category'),
            self::Collection => __('Delete collection'),
        };
    }

    /**
     * Get the label of the success message shown after an entry is updated.
     */
    public function updatedEntryLabel(): string
    {
        return match ($this) {
            self::Author => __('Author updated.'),
            self::Publisher => __('Publisher updated.'),
            self::Category => __('Category updated.'),
            self::Collection => __('Collection updated.'),
        };
    }

    /**
     * Get the label of the success message shown after an entry is deleted.
     */
    public function deletedEntryLabel(): string
    {
        return match ($this) {
            self::Author => __('Author deleted.'),
            self::Publisher => __('Publisher deleted.'),
            self::Category => __('Category deleted.'),
            self::Collection => __('Collection deleted.'),
        };
    }

    /**
     * Get the label of the success message shown after an entry is created.
     */
    public function newEntryLabel(): string
    {
        return match ($this) {
            self::Author => __('Author created.'),
            self::Publisher => __('Publisher created.'),
            self::Category => __('Category created.'),
            self::Collection => __('Collection created.'),
        };
    }

    /**
     * Get the placeholder shown by the catalog picker while nothing is selected.
     */
    public function placeholder(): string
    {
        return match ($this) {
            self::Author => __('Select the authors...'),
            self::Publisher => __('Select a publisher...'),
            self::Category => __('Select a category...'),
            self::Collection => __('Select a collection...'),
        };
    }
}
