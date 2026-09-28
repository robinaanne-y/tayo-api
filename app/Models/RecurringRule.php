<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['frequency', 'interval', 'by_day', 'ends_at', 'occurrence_count'])]
class RecurringRule extends Model
{
    protected function casts(): array
    {
        return [
            'by_day' => 'array',
            'ends_at' => 'datetime',
        ];
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }
}
