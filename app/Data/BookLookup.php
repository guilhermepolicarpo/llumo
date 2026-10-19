<?php

namespace App\Data;

/**
 * What a public catalog knows about a book, found by its ISBN. Every field may be missing.
 */
readonly class BookLookup
{
    /**
     * The spiritual author names are those of the author names a catalog marked as the spirit who dictated the book.
     *
     * @param  list<string>  $authorNames
     * @param  list<string>  $spiritualAuthorNames
     */
    public function __construct(
        public ?string $title = null,
        public ?string $subtitle = null,
        public array $authorNames = [],
        public array $spiritualAuthorNames = [],
        public ?string $publisherName = null,
        public ?int $publishedYear = null,
        public ?int $pageCount = null,
        public ?int $editionNumber = null,
        public ?string $collectionName = null,
        public ?string $synopsis = null,
        public ?string $coverUrl = null,
    ) {
        //
    }
}
