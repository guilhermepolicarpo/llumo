<?php

namespace App\Actions\Library;

use Illuminate\Support\Facades\Storage;

class PruneBookCoverDownloads
{
    /**
     * Delete the covers downloaded more than a day ago for books that were never saved.
     */
    public function handle(): void
    {
        $disk = Storage::disk('public');
        $dayAgo = now()->subDay()->getTimestamp();

        foreach ($disk->files(DownloadBookCover::DIRECTORY) as $path) {
            if ($disk->lastModified($path) < $dayAgo) {
                $disk->delete($path);
            }
        }
    }
}
