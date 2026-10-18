<?php

namespace App\Actions\Library;

use App\Rules\BookRules;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class DownloadBookCover
{
    /**
     * The largest cover accepted, in bytes.
     */
    public const int MAX_BYTES = 5 * 1024 * 1024;

    /**
     * The image types accepted, keyed by the extension they are stored with.
     *
     * @var array<string, int>
     */
    private const array IMAGE_TYPES = ['jpg' => IMAGETYPE_JPEG, 'png' => IMAGETYPE_PNG, 'webp' => IMAGETYPE_WEBP];

    /**
     * Download a cover found by an ISBN lookup and store it on the public disk, returning its path, or null
     * when it cannot be downloaded or is not an acceptable image.
     *
     * Only URLs handed out by the catalogs the lookup asks are given here, never one typed by a user.
     */
    public function handle(string $url): ?string
    {
        if (! Str::startsWith($url, 'https://')) {
            return null;
        }

        try {
            $response = Http::timeout(10)
                ->withOptions(['allow_redirects' => ['max' => 3, 'protocols' => ['https']]])
                ->get($url);
        } catch (Throwable) {
            return null;
        }

        $image = $response->successful() ? $response->body() : '';

        if ($image === '' || strlen($image) > self::MAX_BYTES) {
            return null;
        }

        $size = @getimagesizefromstring($image);
        $extension = $size === false ? false : array_search($size[2], self::IMAGE_TYPES, true);

        if ($extension === false || $size[0] > BookRules::COVER_MAX_DIMENSION || $size[1] > BookRules::COVER_MAX_DIMENSION) {
            return null;
        }

        $path = 'book-covers/'.Str::random(40).'.'.$extension;

        return Storage::disk('public')->put($path, $image) ? $path : null;
    }
}
