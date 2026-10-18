<?php

namespace App\Actions\Library;

use Illuminate\Http\UploadedFile;
use RuntimeException;

class StoreBookCover
{
    /**
     * Store an uploaded book cover on the public disk, returning its path.
     */
    public function handle(UploadedFile $cover): string
    {
        $path = $cover->store('book-covers', 'public');

        if (! is_string($path)) {
            throw new RuntimeException(__('The book cover could not be stored.'));
        }

        return $path;
    }
}
