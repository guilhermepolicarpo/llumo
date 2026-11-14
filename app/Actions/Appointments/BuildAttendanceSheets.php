<?php

namespace App\Actions\Appointments;

use App\Enums\AttendanceSheetSection;
use App\Models\Appointment;
use App\Models\Team;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Str;
use Spatie\LaravelPdf\Enums\Format;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

class BuildAttendanceSheets
{
    /**
     * Build the printable attendance sheets of the given appointments, one front and back A5 sheet each, shown inline.
     * A completed appointment's sheet comes filled in with its record; a blank one prints only the sections, guidances
     * and passes the team chose to print.
     *
     * @param  Collection<int, Appointment>  $appointments
     */
    public function handle(Team $team, Collection $appointments): PdfBuilder
    {
        $appointments->loadMissing(['assistedPerson', 'record.mentor', 'record.fluidicRemedies', 'record.guidances', 'record.passPrescriptions.passType']);

        return Pdf::view('pdf.attendance-sheets', [
            'team' => $team,
            'logo' => $team->logoDataUri(),
            'appointments' => $appointments,
            'hiddenSections' => $team->attendance_sheet_hidden_sections ?? collect(),
            'guidances' => $this->blankSheetEntries($team, AttendanceSheetSection::Guidances),
            'passTypes' => $this->blankSheetEntries($team, AttendanceSheetSection::Passes),
        ])
            ->format(Format::A5)
            ->inline($this->fileName($appointments));
    }

    /**
     * Get the names, keyed by id, of the catalog entries a blank sheet lists in the given section: the ones the team
     * prints, or none when it hides the section.
     *
     * @return SupportCollection<int, string>
     */
    protected function blankSheetEntries(Team $team, AttendanceSheetSection $section): SupportCollection
    {
        if (! $team->printsOnAttendanceSheet($section)) {
            return collect();
        }

        return $team->{$section->catalog()->relationName()}()
            ->where('printed_on_attendance_sheet', true)
            ->orderBy('name')
            ->pluck('name', 'id');
    }

    /**
     * Name the file after the assisted person when printing a single sheet, or after the day when printing several.
     *
     * @param  Collection<int, Appointment>  $appointments
     */
    protected function fileName(Collection $appointments): string
    {
        $count = $appointments->count();
        $subject = $count === 1
            ? $appointments->first()->assistedPerson->name
            : $appointments->first()->scheduled_on->format('Y-m-d');

        return Str::slug(trans_choice('Attendance sheet|Attendance sheets', $count).' '.$subject).'.pdf';
    }
}
