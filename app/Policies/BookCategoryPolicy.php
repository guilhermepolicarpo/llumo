<?php

namespace App\Policies;

use App\Concerns\AuthorizesCatalogEntries;

class BookCategoryPolicy
{
    use AuthorizesCatalogEntries;
}
