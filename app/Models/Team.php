<?php

namespace App\Models;

use App\Concerns\GeneratesUniqueTeamSlugs;
use App\Concerns\HasFormattedAddress;
use App\Enums\BrazilianState;
use App\Enums\TeamRole;
use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property bool $is_personal
 * @property string|null $logo_path
 * @property string|null $postal_code
 * @property string|null $street
 * @property string|null $number
 * @property string|null $complement
 * @property string|null $district
 * @property string|null $city
 * @property BrazilianState|null $state
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read string|null $logo_url
 * @property-read string|null $formatted_address
 * @property-read Collection<int, TeamInvitation> $invitations
 * @property-read Collection<int, Membership> $memberships
 * @property-read Collection<int, User> $members
 * @property-read Collection<int, AssistedPerson> $assistedPeople
 * @property-read Collection<int, AppointmentType> $appointmentTypes
 * @property-read Collection<int, Appointment> $appointments
 * @property-read Collection<int, Mentor> $mentors
 * @property-read Collection<int, FluidicRemedy> $fluidicRemedies
 * @property-read Collection<int, Guidance> $guidances
 * @property-read Collection<int, PassType> $passTypes
 */
#[Fillable([
    'name',
    'slug',
    'is_personal',
    'logo_path',
    'postal_code',
    'street',
    'number',
    'complement',
    'district',
    'city',
    'state',
])]
#[RouteKey('slug')]
class Team extends Model
{
    /** @use HasFactory<TeamFactory> */
    use GeneratesUniqueTeamSlugs, HasFactory, HasFormattedAddress, SoftDeletes;

    /**
     * Bootstrap the model and its traits.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Team $team) {
            if (empty($team->slug)) {
                $team->slug = static::generateUniqueTeamSlug($team->name);
            }
        });

        static::updating(function (Team $team) {
            if ($team->isDirty('name')) {
                $team->slug = static::generateUniqueTeamSlug($team->name, $team->id);
            }
        });
    }

    /**
     * Get the team owner.
     */
    public function owner(): ?Model
    {
        return $this->members()
            ->wherePivot('role', TeamRole::Owner->value)
            ->first();
    }

    /**
     * Get all members of this team.
     *
     * @return BelongsToMany<User, $this, Membership, 'pivot'>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_members', 'team_id', 'user_id')
            ->using(Membership::class)
            ->withPivot(['role'])
            ->withTimestamps();
    }

    /**
     * Get all memberships for this team.
     *
     * @return HasMany<Membership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /**
     * Get all invitations for this team.
     *
     * @return HasMany<TeamInvitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(TeamInvitation::class);
    }

    /**
     * Get all assisted people belonging to this team.
     *
     * @return HasMany<AssistedPerson, $this>
     */
    public function assistedPeople(): HasMany
    {
        return $this->hasMany(AssistedPerson::class);
    }

    /**
     * Get all appointment types belonging to this team.
     *
     * @return HasMany<AppointmentType, $this>
     */
    public function appointmentTypes(): HasMany
    {
        return $this->hasMany(AppointmentType::class);
    }

    /**
     * Get all appointments belonging to this team.
     *
     * @return HasMany<Appointment, $this>
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /**
     * Get all mentors belonging to this team.
     *
     * @return HasMany<Mentor, $this>
     */
    public function mentors(): HasMany
    {
        return $this->hasMany(Mentor::class);
    }

    /**
     * Get all fluidic remedies belonging to this team.
     *
     * @return HasMany<FluidicRemedy, $this>
     */
    public function fluidicRemedies(): HasMany
    {
        return $this->hasMany(FluidicRemedy::class);
    }

    /**
     * Get all guidances belonging to this team.
     *
     * @return HasMany<Guidance, $this>
     */
    public function guidances(): HasMany
    {
        return $this->hasMany(Guidance::class);
    }

    /**
     * Get all pass types belonging to this team.
     *
     * @return HasMany<PassType, $this>
     */
    public function passTypes(): HasMany
    {
        return $this->hasMany(PassType::class);
    }

    /**
     * Get the publicly accessible URL for the team's logo.
     *
     * @return Attribute<string|null, never>
     */
    protected function logoUrl(): Attribute
    {
        return Attribute::make(get: fn (): ?string => $this->logo_path
            ? Storage::disk('public')->url($this->logo_path)
            : null);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_personal' => 'boolean',
            'state' => BrazilianState::class,
        ];
    }
}
