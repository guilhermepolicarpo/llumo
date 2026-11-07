<?php

namespace App\Actions\Appointments;

use App\Models\Appointment;
use App\Models\PassType;
use App\Models\Team;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\LaravelPdf\Enums\Format;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

class BuildRemotePassSheet
{
    /**
     * Build the printable sheet of the remote passes prescribed on the appointment's record as it stands, shown inline:
     * the top part, one card per pass type, stays in the pass room; the bottom part, cut off, tells the assisted person
     * when to rest to receive each pass.
     */
    public function handle(Team $team, Appointment $appointment): PdfBuilder
    {
        $appointment->loadMissing('assistedPerson');

        $passes = $appointment->remotePasses();

        return Pdf::view('pdf.remote-pass-sheet', [
            'team' => $team,
            'logo' => $team->logoDataUri(),
            'appointment' => $appointment,
            'assistedPerson' => $appointment->assistedPerson,
            'passes' => $passes,
            'schedule' => $this->schedule($passes),
        ])
            ->format(Format::A4)
            ->inline(Str::slug(__('Remote passes').' '.$appointment->assistedPerson->name).'.pdf');
    }

    /**
     * List every remote pass in date order, for the assisted person, as in "Wednesday, 11 of November", with the time
     * they rest for it. Passes whose date was left blank come last, with a blank date to be written by hand.
     *
     * @param  Collection<int, array{passType: PassType, quantity: int, dates: list<CarbonImmutable>}>  $passes
     * @return Collection<int, array{date: ?string, weekday: ?string, time: ?string}>
     */
    protected function schedule(Collection $passes): Collection
    {
        $dated = collect();
        $undated = collect();

        foreach ($passes as $pass) {
            $time = $pass['passType']->remoteTimeLabel();

            foreach ($pass['dates'] as $day) {
                $dated->push(['day' => $day, 'time' => $time]);
            }

            $undated->push(...array_fill(0, max(0, $pass['quantity'] - count($pass['dates'])), ['date' => null, 'weekday' => null, 'time' => $time]));
        }

        return $dated
            ->sortBy('day')
            ->map(fn (array $pass): array => [
                'date' => __(':day of :month', ['day' => $pass['day']->day, 'month' => $pass['day']->translatedFormat('F')]),
                'weekday' => Str::ucfirst(Str::before($pass['day']->translatedFormat('l'), '-')),
                'time' => $pass['time'],
            ])
            ->concat($undated)
            ->values();
    }
}
