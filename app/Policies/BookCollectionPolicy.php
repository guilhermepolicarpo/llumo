<?php

namespace App\Policies;

use App\Concerns\AuthorizesCatalogEntries;

class BookCollectionPolicy
{
    use AuthorizesCatalogEntries;
}
