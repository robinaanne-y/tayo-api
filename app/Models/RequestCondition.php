<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['permission_request_id', 'created_by_member_id', 'description'])]
class RequestCondition extends Model
{
    public function permissionRequest(): BelongsTo
    {
        return $this->belongsTo(PermissionRequest::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'created_by_member_id');
    }
}
