<?php

namespace App\Actions\Appointments;

use App\Models\Appointment;
use App\Models\Team;
use finfo;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\LaravelPdf\Enums\Format;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

class BuildAttendanceSheets
{
    /**
     * Build the printable attendance sheets of the given appointments, one front and back A5 sheet each, shown inline.
     *
     * @param  Collection<int, Appointment>  $appointments
     */
    public function handle(Team $team, Collection $appointments): PdfBuilder
    {
        $appointments->loadMissing('assistedPerson');

        return Pdf::view('pdf.attendance-sheets', [
            'team' => $team,
            'logo' => $this->logoDataUri($team),
            'appointments' => $appointments,
            'guidances' => $team->guidances()->orderBy('name')->pluck('name'),
            'passTypes' => $team->passTypes()->orderBy('name')->pluck('name'),
        ])
            ->format(Format::A5)
            ->inline($this->fileName($appointments));
    }

    /**
     * Embed the team's logo as a data URI, so the PDF renderer never has to reach the disk or the network for it.
     */
    protected function logoDataUri(Team $team): ?string
    {
        $contents = $team->logo_path ? Storage::disk('public')->get($team->logo_path) : null;

        if ($contents === null) {
            return null;
        }

        return 'data:'.(new finfo(FILEINFO_MIME_TYPE))->buffer($contents).';base64,'.base64_encode($contents);
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
