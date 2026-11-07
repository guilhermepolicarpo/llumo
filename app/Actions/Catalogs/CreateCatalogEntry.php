<?php

namespace App\Actions\Catalogs;

use App\Enums\Catalog;
use App\Enums\LibraryCatalog;
use App\Models\Team;
use Illuminate\Database\Eloquent\Model;

class CreateCatalogEntry
{
    /**
     * Create a new entry in one of the given team's catalogs, with any other attributes the catalog keeps.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Team $team, Catalog|LibraryCatalog $catalog, string $name, array $attributes = []): Model
    {
        return $team->{$catalog->relationName()}()->create([
            ...$attributes,
            'name' => trim($name),
        ]);
    }
}
