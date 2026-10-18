<?php

namespace App\Concerns;

use Illuminate\Support\Str;

/**
 * Address the cases of a catalog enum in URLs by a slug derived from their team relationship name.
 */
trait AddressedBySlug
{
    /**
     * Find the catalog addressed by the given URL segment.
     */
    public static function fromSlug(string $slug): ?self
    {
        return collect(self::cases())->first(fn (self $catalog) => $catalog->slug() === $slug);
    }

    /**
     * Get the URL segment of the catalog's management page, derived from its team relationship name.
     */
    public function slug(): string
    {
        return Str::kebab($this->relationName());
    }
}
