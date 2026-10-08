<?php

use App\Http\Controllers\MediaController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Serves avatars/trip thumbnails through Laravel (see MediaController) so
// CORS headers are actually applied -- restricted to the two upload
// subdirectories and the mime types UploadTripThumbnailRequest /
// UpdateMemberAvatarRequest accept.
Route::get('/media/{path}', [MediaController::class, 'show'])
    ->where('path', '(avatars|trip-thumbnails)/[\w-]+\.(jpe?g|png|webp)')
    ->name('media.show');
