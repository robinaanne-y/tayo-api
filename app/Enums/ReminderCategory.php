<?php

namespace App\Enums;

enum ReminderCategory: string
{
    case MealPlanning = 'meal_planning';
    case Grocery = 'grocery';
    case TripPrep = 'trip_prep';
}
