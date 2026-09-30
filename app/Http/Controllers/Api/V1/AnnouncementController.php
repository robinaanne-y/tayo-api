<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Announcements\StoreAnnouncementRequest;
use App\Http\Resources\AnnouncementResource;
use App\Models\Announcement;
use App\Models\Household;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    public function index(Request $request, Household $household): JsonResponse
    {
        $this->authorize('view', $household);

        $announcements = $household->announcements()->with('author')->latest()->get();

        return response()->json([
            'data' => AnnouncementResource::collection($announcements),
        ]);
    }

    public function store(StoreAnnouncementRequest $request, Household $household): JsonResponse
    {
        $announcement = $household->announcements()->create([
            'author_member_id' => $request->user()->member->id,
            'content' => $request->validated('content'),
        ]);

        $announcement->load('author');

        return response()->json([
            'data' => AnnouncementResource::make($announcement),
        ], 201);
    }

    private function announcementFor(Household $household, Announcement $announcement): Announcement
    {
        abort_if($announcement->household_id !== $household->id, 404);

        return $announcement;
    }

    public function destroy(Request $request, Household $household, Announcement $announcement): JsonResponse
    {
        $announcement = $this->announcementFor($household, $announcement);

        $this->authorize('deleteAnnouncement', [$household, $announcement]);

        $announcement->delete();

        return response()->json(null, 204);
    }
}
