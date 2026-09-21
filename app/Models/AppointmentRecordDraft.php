<?php

namespace App\Models;

use Database\Factories\AppointmentRecordDraftFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $appointment_id
 * @property int|null $user_id
 * @property array<string, mixed> $data
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Appointment $appointment
 * @property-read User|null $user
 */
#[Fillable([
    'appointment_id',
    'user_id',
    'data',
])]
class AppointmentRecordDraft extends Model
{
    /** @use HasFactory<AppointmentRecordDraftFactory> */
    use HasFactory;

    /**
     * Get the appointment whose record this draft belongs to.
     *
     * @return BelongsTo<Appointment, $this>
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /**
     * Get the user who last changed the draft.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data' => 'array',
        ];
    }
}
