<?php

namespace App\Concerns;

use Illuminate\Support\Str;

trait ComparesNames
{
    /**
     * Get the key two spellings of the same name share, whatever their case, accents and spacing, as catalogs
     * spell names differently.
     */
    protected function nameKey(string $name): string
    {
        return Str::lower(Str::ascii(Str::squish($name)));
    }
}
