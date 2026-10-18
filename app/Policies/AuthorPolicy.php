<?php

namespace App\Policies;

use App\Concerns\AuthorizesCatalogEntries;

class AuthorPolicy
{
    use AuthorizesCatalogEntries;
}
