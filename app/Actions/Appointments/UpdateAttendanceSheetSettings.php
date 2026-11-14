<?php

namespace App\Actions\Appointments;

use App\Enums\AttendanceSheetSection;
use App\Models\Team;
use Illuminate\Support\Facades\DB;

class UpdateAttendanceSheetSettings
{
    /**
     * Choose what the team prints on the blank attendance sheet: the sections, and which entries of each catalog it lists.
     * The sections left out are the ones stored, so a section added later is printed until the team hides it.
     *
     * @param  array{hidden_sections: array<int, AttendanceSheetSection>, printed_entry_ids: array<string, array<int, int|string>>}  $settings
     */
    public function handle(Team $team, array $settings): Team
    {
        DB::transaction(function () use ($team, $settings) {
            $team->update(['attendance_sheet_hidden_sections' => $settings['hidden_sections'] ?: null]);

            foreach (AttendanceSheetSection::withCatalog() as $section) {
                $entries = $team->{$section->catalog()->relationName()}();
                $printedIds = $settings['printed_entry_ids'][$section->value] ?? [];

                $entries->clone()->whereKey($printedIds)->update(['printed_on_attendance_sheet' => true]);
                $entries->clone()->whereKeyNot($printedIds)->update(['printed_on_attendance_sheet' => false]);
            }
        });

        return $team;
    }
}
