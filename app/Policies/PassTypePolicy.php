<?php

namespace App\Policies;

use App\Concerns\AuthorizesCatalogEntries;

class PassTypePolicy
{
    use AuthorizesCatalogEntries;
}
