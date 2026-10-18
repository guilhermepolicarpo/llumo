<?php

namespace App\Data;

/**
 * What a public catalog knows about a book, found by its ISBN. Every field may be missing.
 */
readonly class BookLookup
{
    /**
     * @param  list<string>  $authorNames
     */
    public function __construct(
        public ?string $title = null,
        public ?string $subtitle = null,
        public array $authorNames = [],
        public ?string $publisherName = null,
        public ?int $publishedYear = null,
        public ?int $pageCount = null,
        public ?string $synopsis = null,
        public ?string $coverUrl = null,
    ) {
        //
    }
}
