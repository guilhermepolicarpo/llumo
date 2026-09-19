<?php

use App\Actions\Appointments\MarkMissedAppointmentsAsNoShow;
use App\Models\TeamInvitation;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
    TeamInvitation::query()
        ->whereNotNull('expires_at')
        ->where('expires_at', '<', now())
        ->delete();
})->daily()->description('Delete expired team invitations');

Schedule::call(function (MarkMissedAppointmentsAsNoShow $markMissedAppointmentsAsNoShow) {
    $markMissedAppointmentsAsNoShow->handle();
})->dailyAt('00:05')->description('Mark missed appointments as no-show');
