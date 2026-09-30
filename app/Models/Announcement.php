<?php

namespace App\Models;

use Database\Factories\AnnouncementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['household_id', 'author_member_id', 'content'])]
class Announcement extends Model
{
    /** @use HasFactory<AnnouncementFactory> */
    use HasFactory;

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'author_member_id');
    }
}
