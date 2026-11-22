<?php

namespace App\Actions\Appointments;

use App\Enums\AttendanceSheetSection;
use App\Models\Appointment;
use App\Models\Team;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Str;
use Spatie\LaravelPdf\Enums\Format;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

class BuildAttendanceSheets
{
    /**
     * Build the printable attendance sheets of the given appointments, one front and back A4 sheet each, shown inline.
     * A completed appointment's sheet comes filled in with its record; a blank one prints only the sections, guidances
     * and passes the team chose to print. The appointments without a sheet to fill follow the sheets as a list, one
     * group per appointment type.
     *
     * @param  Collection<int, Appointment>  $appointments
     * @param  Collection<int, Appointment>  $listedAppointments
     */
    public function handle(Team $team, Collection $appointments, Collection $listedAppointments = new Collection): PdfBuilder
    {
        $appointments->loadMissing(['assistedPerson', 'record.mentor', 'record.fluidicRemedies', 'record.guidances', 'record.passPrescriptions.passType']);
        $listedAppointments->loadMissing(['appointmentType', 'assistedPerson']);
        $listDate = $listedAppointments->first()?->scheduled_on;

        return Pdf::view('pdf.attendance-sheets', [
            'team' => $team,
            'logo' => $team->logoDataUri(),
            'appointments' => $appointments,
            'hiddenSections' => $team->attendance_sheet_hidden_sections ?? collect(),
            'guidances' => $this->blankSheetEntries($team, AttendanceSheetSection::Guidances),
            'passTypes' => $this->blankSheetEntries($team, AttendanceSheetSection::Passes),
            'listGroups' => $this->listGroups($listedAppointments),
            'listDate' => $listDate,
        ])
            ->format(Format::A4)
            ->inline($this->fileName($appointments, $listDate));
    }

    /**
     * Group the listed appointments by the name of their type, in alphabetical order, each group sorted by the name of
     * the assisted person.
     *
     * @param  Collection<int, Appointment>  $listedAppointments
     * @return SupportCollection<string, SupportCollection<int, Appointment>>
     */
    protected function listGroups(Collection $listedAppointments): SupportCollection
    {
        return $listedAppointments->toBase()
            ->groupBy(fn (Appointment $appointment): string => $appointment->appointmentType->name)
            ->sortKeys(SORT_NATURAL | SORT_FLAG_CASE)
            ->map(fn (SupportCollection $group): SupportCollection => $group
                ->sortBy(fn (Appointment $appointment): string => $appointment->assistedPerson->name, SORT_NATURAL | SORT_FLAG_CASE)
                ->values());
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
     * Name the file after the assisted person when printing a single sheet, or after the day when printing several
     * sheets, the list, or both.
     *
     * @param  Collection<int, Appointment>  $appointments
     */
    protected function fileName(Collection $appointments, ?CarbonInterface $listDate): string
    {
        if ($listDate) {
            $title = $appointments->isEmpty() ? __('Appointment list') : __('Sheets and list');

            return Str::slug($title.' '.$listDate->format('Y-m-d')).'.pdf';
        }

        $count = $appointments->count();
        $subject = $count === 1
            ? $appointments->first()->assistedPerson->name
            : $appointments->first()->scheduled_on->format('Y-m-d');

        return Str::slug(trans_choice('Attendance sheet|Attendance sheets', $count).' '.$subject).'.pdf';
    }
}
