<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Streams user-uploaded media (avatars, trip thumbnails) through Laravel
 * instead of the raw /storage symlink. `php artisan serve`'s built-in PHP
 * server serves an existing /storage/* file directly off disk and skips
 * the framework entirely -- including the CORS middleware -- which breaks
 * loading these images from the Flutter web build (a different origin).
 * Routing through a controller guarantees the request always goes through
 * Laravel, so CORS headers are always applied.
 */
class MediaController extends Controller
{
    public function show(string $path): Response
    {
        abort_unless(Storage::disk('public')->exists($path), 404);

        return Storage::disk('public')->response($path);
    }
}
