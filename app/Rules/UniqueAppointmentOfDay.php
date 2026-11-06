<?php

namespace App\Rules;

use App\Concerns\ParsesFormDates;
use App\Models\Team;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class UniqueAppointmentOfDay implements DataAwareRule, ValidationRule
{
    use ParsesFormDates;

    /**
     * The data under validation, which holds the chosen assisted person and appointment type.
     *
     * @var array<string, mixed>
     */
    protected array $data = [];

    public function __construct(protected Team $team, protected ?int $ignoreAppointmentId = null)
    {
        //
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Fail when the assisted person already has an appointment of the same type on the date, unless it was canceled.
     *
     * Missing or invalid values are left to the rules of their own fields.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $assistedPersonId = $this->data['assistedPersonId'] ?? null;
        $appointmentTypeId = $this->data['appointmentTypeId'] ?? null;

        $day = is_string($value) ? $this->dateFrom($value) : null;

        if (! is_numeric($assistedPersonId) || ! is_numeric($appointmentTypeId) || $day === null) {
            return;
        }

        $sameAppointment = $this->team->appointments()
            ->with('appointmentType:id,name')
            ->sameDayAs((int) $assistedPersonId, (int) $appointmentTypeId, $day, $this->ignoreAppointmentId)
            ->first(['id', 'appointment_type_id']);

        if ($sameAppointment !== null) {
            $fail(__('This assisted person already has a :type appointment on this day.', [
                'type' => $sameAppointment->appointmentType->name,
            ]));
        }
    }
}
