<?php

namespace App\Policies;

use App\Concerns\AuthorizesCatalogEntries;

class PublisherPolicy
{
    use AuthorizesCatalogEntries;
}
