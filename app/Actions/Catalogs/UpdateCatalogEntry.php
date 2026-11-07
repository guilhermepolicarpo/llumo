<?php

namespace App\Actions\Catalogs;

use Illuminate\Database\Eloquent\Model;

class UpdateCatalogEntry
{
    /**
     * Rename an entry of one of a team's catalogs, updating any other attributes the catalog keeps.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Model $entry, string $name, array $attributes = []): Model
    {
        $entry->update([
            ...$attributes,
            'name' => trim($name),
        ]);

        return $entry;
    }
}
