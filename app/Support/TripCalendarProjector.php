<?php

namespace App\Support;

use App\Http\Resources\TripCalendarEntryResource;
use App\Models\Household;
use Carbon\Carbon;

/**
 * Projects a household's trips and itinerary items into read-only,
 * non-persisted calendar entries -- the same "computed live at read time,
 * never stored" technique EventController::index() already uses for its
 * all_member_households visibility branch. No `events` row is ever
 * created for a trip; Trip/TripItineraryItem stay the sole source of
 * truth, and this class is just a projection over them.
 */
class TripCalendarProjector
{
    /**
     * @return array<int, array<string, mixed>> raw, already-resolved arrays shaped like EventResource output
     */
    public function project(Household $household, ?Carbon $from, ?Carbon $to): array
    {
        $trips = $household->trips()
            ->when($from, fn ($q) => $q->where(function ($q2) use ($from) {
                $q2->where('end_at', '>=', $from)
                    ->orWhere(function ($q3) use ($from) {
                        $q3->whereNull('end_at')->where('start_at', '>=', $from);
                    });
            }))
            ->when($to, fn ($q) => $q->where('start_at', '<=', $to))
            ->with(['itineraryItems', 'participants'])
            ->get();

        $entries = [];

        foreach ($trips as $trip) {
            $entries[] = TripCalendarEntryResource::forTripSpan($trip)->resolve();

            foreach ($trip->itineraryItems as $item) {
                if ($from !== null && $item->scheduled_at->lessThan($from)) {
                    continue;
                }

                if ($to !== null && $item->scheduled_at->greaterThan($to)) {
                    continue;
                }

                $entries[] = TripCalendarEntryResource::forItineraryItem($trip, $item)->resolve();
            }
        }

        return $entries;
    }
}
