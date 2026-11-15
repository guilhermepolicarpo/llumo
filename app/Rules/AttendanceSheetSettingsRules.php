<?php

namespace App\Rules;

use App\Enums\AttendanceSheetSection;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;

class AttendanceSheetSettingsRules
{
    /**
     * Get the validation rules used to validate what a team prints on the blank attendance sheet: the sections and,
     * of the sections listing a catalog, which entries are listed.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string|object>>
     */
    public static function all(Team $team): array
    {
        $rules = [
            'sections' => ['required', 'array:'.implode(',', array_column(AttendanceSheetSection::cases(), 'value'))],
            'sections.*' => ['boolean'],
            'printedEntryIds' => ['array'],
        ];

        foreach (AttendanceSheetSection::withCatalog() as $section) {
            $rules["printedEntryIds.{$section->value}"] = ['array'];
            $rules["printedEntryIds.{$section->value}.*"] = CatalogRules::entryId($team, $section->catalog());
        }

        return $rules;
    }
}
