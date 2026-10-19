<?php

use Illuminate\Support\Facades\Storage;

test('covers downloaded more than a day ago for books never saved are deleted by the scheduled cleanup', function () {
    $this->travelTo(now()->startOfDay());
    Storage::fake('public');

    Storage::disk('public')->put('book-covers/downloads/abandoned.jpg', 'cover');
    touch(Storage::disk('public')->path('book-covers/downloads/abandoned.jpg'), now()->subDay()->subMinute()->getTimestamp());
    Storage::disk('public')->put('book-covers/downloads/recent.jpg', 'cover');
    Storage::disk('public')->put('book-covers/saved.jpg', 'cover');
    touch(Storage::disk('public')->path('book-covers/saved.jpg'), now()->subWeek()->getTimestamp());

    $this->artisan('schedule:run')->assertSuccessful();

    Storage::disk('public')->assertMissing('book-covers/downloads/abandoned.jpg');
    Storage::disk('public')->assertExists('book-covers/downloads/recent.jpg');
    Storage::disk('public')->assertExists('book-covers/saved.jpg');
});
