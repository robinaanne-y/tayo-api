<?php

namespace App\Enums;

enum HouseholdRole: string
{
    case Owner = 'owner';
    case Adult = 'adult';
    case Minor = 'minor';
    case Child = 'child';

    /**
     * Roles trusted to manage household settings and members.
     */
    public function canManageHousehold(): bool
    {
        return match ($this) {
            self::Owner, self::Adult => true,
            self::Minor, self::Child => false,
        };
    }
}
