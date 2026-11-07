<?php

namespace App\Models;

use Database\Factories\PassSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A day on which the assisted person receives one of the remote passes prescribed to them.
 *
 * @property int $id
 * @property int $pass_prescription_id
 * @property Carbon $scheduled_on
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read PassPrescription $passPrescription
 */
#[Fillable([
    'pass_prescription_id',
    'scheduled_on',
])]
class PassSession extends Model
{
    /** @use HasFactory<PassSessionFactory> */
    use HasFactory;

    /**
     * Get the prescription this session belongs to.
     *
     * @return BelongsTo<PassPrescription, $this>
     */
    public function passPrescription(): BelongsTo
    {
        return $this->belongsTo(PassPrescription::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scheduled_on' => 'date',
        ];
    }
}
