<?php

namespace App\Models;

use Database\Factories\AuthorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property string $name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Team $team
 * @property-read Collection<int, Book> $books
 */
#[Fillable([
    'team_id',
    'name',
])]
class Author extends Model
{
    /** @use HasFactory<AuthorFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the team this author belongs to.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the books this author wrote, psychographed or dictated, with the role on the pivot.
     *
     * @return BelongsToMany<Book, $this, Pivot, 'authorship'>
     */
    public function books(): BelongsToMany
    {
        return $this->belongsToMany(Book::class)->as('authorship')->withPivot('role', 'position');
    }
}
