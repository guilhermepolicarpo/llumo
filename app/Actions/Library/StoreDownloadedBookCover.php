<?php

namespace App\Actions\Library;

use Illuminate\Support\Facades\Storage;

class StoreDownloadedBookCover
{
    /**
     * Keep a cover downloaded ahead of saving the book among the book covers, returning its new path, or null
     * when there is none or it is no longer there, as when it was pruned.
     */
    public function handle(?string $downloadedPath): ?string
    {
        $disk = Storage::disk('public');

        if ($downloadedPath === null || ! $disk->exists($downloadedPath)) {
            return null;
        }

        $path = StoreBookCover::DIRECTORY.'/'.basename($downloadedPath);

        return $disk->move($downloadedPath, $path) ? $path : null;
    }
}
