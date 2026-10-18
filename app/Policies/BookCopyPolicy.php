<?php

namespace App\Policies;

use App\Concerns\AuthorizesCatalogEntries;

/**
 * Any member may add copies of a book, but only members allowed to manage catalogs may change a copy's
 * status or delete it.
 */
class BookCopyPolicy
{
    use AuthorizesCatalogEntries;
}
