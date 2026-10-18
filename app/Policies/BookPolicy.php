<?php

namespace App\Policies;

use App\Concerns\AuthorizesCatalogEntries;
use App\Models\Book;
use App\Models\User;

/**
 * Any member may browse the library and register books, but only members allowed to manage catalogs
 * may edit or delete them.
 */
class BookPolicy
{
    use AuthorizesCatalogEntries;

    /**
     * Determine whether the user can view the book, its copies and its loans.
     */
    public function view(User $user, Book $book): bool
    {
        return $user->belongsToTeam($book->team);
    }
}
