<?php

namespace App\Actions\Catalogs;

use Illuminate\Database\Eloquent\Model;

class UpdateCatalogEntry
{
    /**
     * Rename an entry of one of a team's catalogs.
     */
    public function handle(Model $entry, string $name): Model
    {
        $entry->update([
            'name' => trim($name),
        ]);

        return $entry;
    }
}
