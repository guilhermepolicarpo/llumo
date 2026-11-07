<?php

use App\Actions\Appointments\CancelExpiredRemoteAppointments;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;

test('remote appointments never entered are canceled once their record can no longer be entered', function () {
    $this->travelTo('2026-09-20 00:05:00');
    $expired = Appointment::factory()->remote()->create(['scheduled_on' => '2026-09-12']);
    $lastDayOfWindow = Appointment::factory()->remote()->create(['scheduled_on' => '2026-09-13']);

    $canceled = app(CancelExpiredRemoteAppointments::class)->handle();

    expect($canceled)->toBe(1)
        ->and($expired->fresh()->status)->toBe(AppointmentStatus::Canceled)
        ->and($lastDayOfWindow->fresh()->status)->toBe(AppointmentStatus::Scheduled);
});

test('in-person appointments and remote records already started are left untouched', function () {
    $this->travelTo('2026-09-20 00:05:00');
    $inPerson = Appointment::factory()->inPerson()->create(['scheduled_on' => '2026-09-12']);
    $started = Appointment::factory()->remote()->inProgress()->create(['scheduled_on' => '2026-09-12']);

    app(CancelExpiredRemoteAppointments::class)->handle();

    expect($inPerson->fresh()->status)->toBe(AppointmentStatus::Scheduled)
        ->and($started->fresh()->status)->toBe(AppointmentStatus::InProgress);
});
