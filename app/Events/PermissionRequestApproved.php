<?php

namespace App\Events;

use App\Models\PermissionRequest;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Extension point for Phase 9 (Realtime, Notifications & Automation) to
 * listen to and turn into a push notification / broadcast — no listener
 * exists yet, this just marks where the hook goes.
 */
class PermissionRequestApproved
{
    use Dispatchable, SerializesModels;

    public function __construct(public PermissionRequest $permissionRequest)
    {
    }
}
