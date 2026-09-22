<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notes\StoreFamilyNoteRequest;
use App\Http\Resources\FamilyNoteResource;
use App\Models\FamilyNote;
use App\Models\Household;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FamilyNoteController extends Controller
{
    public function index(Request $request, Household $household): JsonResponse
    {
        $this->authorize('view', $household);

        $notes = $household->familyNotes()->active()->with('author')->latest()->get();

        return response()->json([
            'data' => FamilyNoteResource::collection($notes),
        ]);
    }

    public function store(StoreFamilyNoteRequest $request, Household $household): JsonResponse
    {
        $note = $household->familyNotes()->create([
            'author_member_id' => $request->user()->member->id,
            'content' => $request->validated('content'),
            'expires_at' => now()->addDay(),
        ]);

        $note->load('author');

        return response()->json([
            'data' => FamilyNoteResource::make($note),
        ], 201);
    }

    private function noteFor(Household $household, FamilyNote $note): FamilyNote
    {
        abort_if($note->household_id !== $household->id, 404);

        return $note;
    }

    public function destroy(Request $request, Household $household, FamilyNote $note): JsonResponse
    {
        $note = $this->noteFor($household, $note);

        $this->authorize('deleteNote', [$household, $note]);

        $note->delete();

        return response()->json(null, 204);
    }
}
