<?php

namespace App\Rules;

use App\Enums\Catalog;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class CatalogRules
{
    /**
     * Get the validation rules used to validate a catalog entry's name for the given team, ignoring the entry being edited.
     *
     * @return array<int, ValidationRule|array<mixed>|string|object>
     */
    public static function name(Team $team, Catalog $catalog, ?Model $ignore = null): array
    {
        return [
            'required',
            'string',
            'max:150',
            Rule::unique($catalog->modelClass(), 'name')->where('team_id', $team->id)->withoutTrashed()->ignore($ignore),
        ];
    }

    /**
     * Get the validation rules used to validate a reference to one of the team's catalog entries.
     *
     * @return array<int, ValidationRule|array<mixed>|string|object>
     */
    public static function entryId(Team $team, Catalog $catalog): array
    {
        return [
            'integer',
            Rule::exists($catalog->modelClass(), 'id')->where('team_id', $team->id)->withoutTrashed(),
        ];
    }
}
