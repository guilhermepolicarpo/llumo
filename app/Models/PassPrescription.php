<?php

namespace App\Models;

use App\Enums\AppointmentMode;
use Database\Factories\PassPrescriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $appointment_record_id
 * @property int $pass_type_id
 * @property int $quantity
 * @property AppointmentMode $mode
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read AppointmentRecord $appointmentRecord
 * @property-read PassType $passType
 */
#[Fillable([
    'appointment_record_id',
    'pass_type_id',
    'quantity',
    'mode',
])]
class PassPrescription extends Model
{
    /** @use HasFactory<PassPrescriptionFactory> */
    use HasFactory;

    /**
     * Get the record this prescription belongs to.
     *
     * @return BelongsTo<AppointmentRecord, $this>
     */
    public function appointmentRecord(): BelongsTo
    {
        return $this->belongsTo(AppointmentRecord::class);
    }

    /**
     * Get the prescribed pass type, including deleted types.
     *
     * @return BelongsTo<PassType, $this>
     */
    public function passType(): BelongsTo
    {
        return $this->belongsTo(PassType::class)->withTrashed();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'mode' => AppointmentMode::class,
        ];
    }
}
