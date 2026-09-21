<?php

namespace App\Actions\Catalogs;

use App\Enums\Catalog;
use App\Models\Team;
use Illuminate\Database\Eloquent\Model;

class CreateCatalogEntry
{
    /**
     * Create a new entry in one of the given team's catalogs.
     */
    public function handle(Team $team, Catalog $catalog, string $name): Model
    {
        return $team->{$catalog->relationName()}()->create([
            'name' => trim($name),
        ]);
    }
}
