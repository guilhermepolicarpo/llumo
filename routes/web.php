<?php

use App\Actions\Appointments\BuildAttendanceSheets;
use App\Http\Middleware\EnsureTeamMembership;
use App\Models\Appointment;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->scopeBindings()
    ->group(function () {
        Route::view('dashboard', 'dashboard')->name('dashboard');

        Route::livewire('assisted-people', 'pages::assisted-people.index')->name('assisted-people.index');
        Route::livewire('assisted-people/create', 'pages::assisted-people.create')->name('assisted-people.create');
        Route::livewire('assisted-people/{assistedPerson}', 'pages::assisted-people.show')->name('assisted-people.show');
        Route::livewire('assisted-people/{assistedPerson}/edit', 'pages::assisted-people.edit')->name('assisted-people.edit');

        Route::livewire('appointments', 'pages::appointments.index')->name('appointments.index');
        Route::livewire('appointments/create', 'pages::appointments.create')->name('appointments.create');
        Route::livewire('appointments/{appointment}/edit', 'pages::appointments.edit')->name('appointments.edit');
        Route::livewire('appointments/{appointment}/attend', 'pages::appointments.attend')->name('appointments.attend');

        Route::get('appointments/attendance-sheets', function (Request $request, Team $current_team, BuildAttendanceSheets $buildAttendanceSheets) {
            Gate::authorize('viewAny', [Appointment::class, $current_team]);

            $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);

            $appointments = $current_team->appointments()
                ->scheduledOn($request->date('date', 'Y-m-d') ?? today())
                ->waitingForRecord()
                ->orderBy('received_at')
                ->get();

            abort_if($appointments->isEmpty(), 404);

            return $buildAttendanceSheets->handle($current_team, $appointments);
        })->name('appointments.attendance-sheets');

        Route::get('appointments/{appointment}/attendance-sheet', function (Team $current_team, Appointment $appointment, BuildAttendanceSheets $buildAttendanceSheets) {
            Gate::authorize('printAttendanceSheet', $appointment);

            return $buildAttendanceSheets->handle($current_team, $appointment->newCollection([$appointment]));
        })->name('appointments.attendance-sheet');

        Route::livewire('catalogs/appointment-types', 'pages::catalogs.appointment-types')->name('catalogs.appointment-types');
        Route::livewire('catalogs/{catalog}', 'pages::catalogs.index')->name('catalogs.index');
    });

require __DIR__.'/settings.php';
