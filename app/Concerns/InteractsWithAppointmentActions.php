<?php

namespace App\Concerns;

use App\Actions\Appointments\PerformAppointmentAction;
use App\Enums\AppointmentAction;
use App\Models\Appointment;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

trait InteractsWithAppointmentActions
{
    /**
     * Perform the action on the appointment and report the outcome with a toast.
     */
    protected function performAppointmentAction(PerformAppointmentAction $performAppointmentAction, Appointment $appointment, ?AppointmentAction $action): bool
    {
        abort_if($action === null, 404);

        Gate::authorize('perform', $appointment);

        if (! $performAppointmentAction->handle($appointment, $action, Auth::user())) {
            Flux::toast(variant: 'danger', text: __('This action is no longer available for the appointment.'));

            return false;
        }

        Flux::toast(variant: 'success', text: __('Appointment moved to :status.', ['status' => $action->toStatus()->label()]));

        return true;
    }
}
