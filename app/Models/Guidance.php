<?php

namespace App\Models;

use Database\Factories\GuidanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property string $name
 * @property bool $printed_on_attendance_sheet
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Team $team
 * @property-read Pivot $pivot The record's link to the guidance, carrying its detail, when loaded through a record.
 */
#[Fillable([
    'team_id',
    'name',
    'printed_on_attendance_sheet',
])]
class Guidance extends Model
{
    /** @use HasFactory<GuidanceFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The model's default values for attributes, matching the database defaults.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'printed_on_attendance_sheet' => true,
    ];

    /**
     * Get the team this guidance belongs to.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'printed_on_attendance_sheet' => 'boolean',
        ];
    }
}
