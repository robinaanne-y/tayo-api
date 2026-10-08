<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaControllerTest extends TestCase
{
    public function test_it_streams_an_existing_file_with_cors_headers(): void
    {
        Storage::fake('public');
        Storage::disk('public')->putFileAs('avatars', UploadedFile::fake()->image('a.jpg'), 'existing.jpg');

        $response = $this->get('/media/avatars/existing.jpg');

        $response->assertOk()->assertHeader('Access-Control-Allow-Origin', '*');
    }

    public function test_it_404s_a_missing_file(): void
    {
        Storage::fake('public');

        $this->get('/media/avatars/missing.jpg')->assertNotFound();
    }

    public function test_it_rejects_paths_outside_the_known_upload_directories(): void
    {
        $this->get('/media/../.env')->assertNotFound();
        $this->get('/media/random-dir/file.jpg')->assertNotFound();
    }
}
