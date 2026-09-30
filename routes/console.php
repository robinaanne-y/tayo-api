<?php

use App\Models\FamilyNote;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Not exact-to-the-second — index() already excludes expired notes via
// FamilyNote::scopeActive(), so this just keeps the table from growing
// unbounded rather than being load-bearing for correctness.
Schedule::call(fn () => FamilyNote::query()->where('expires_at', '<=', now())->delete())
    ->hourly();
