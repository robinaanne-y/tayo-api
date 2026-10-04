<?php

namespace App\Enums;

enum TripStatus: string
{
    case Planning = 'planning';
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
