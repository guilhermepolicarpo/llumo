<?php

namespace App\Enums;

/**
 * The part an author played in a book: the incarnate author wrote it, or psychographed it as a medium,
 * while the spiritual author is the spirit who dictated it.
 */
enum AuthorRole: string
{
    case Incarnate = 'incarnate';
    case Spiritual = 'spiritual';

    /**
     * Get the plural label for the role, used to head the list of a book's authors in this role.
     */
    public function pluralLabel(): string
    {
        return match ($this) {
            self::Incarnate => __('Incarnate authors'),
            self::Spiritual => __('Spiritual authors'),
        };
    }
}
