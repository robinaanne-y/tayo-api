<?php

namespace Tests\Feature\Trips;

use App\Enums\HouseholdRole;
use App\Models\Household;
use App\Models\Member;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TripThumbnailTest extends TestCase
{
    use RefreshDatabase;

    private function memberFor(User $user, Household $household, HouseholdRole $role): Member
    {
        $member = Member::factory()->create(['user_id' => $user->id]);

        $household->memberships()->create([
            'member_id' => $member->id,
            'role' => $role,
        ]);

        return $member;
    }

    public function test_an_owner_or_adult_can_upload_a_trip_thumbnail_but_a_minor_cannot(): void
    {
        Storage::fake('public');

        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $ownerMember = $this->memberFor($owner, $household, HouseholdRole::Owner);

        $minorUser = User::factory()->create();
        $this->memberFor($minorUser, $household, HouseholdRole::Minor);

        $trip = Trip::factory()->create([
            'household_id' => $household->id,
            'created_by_member_id' => $ownerMember->id,
        ]);

        $this->actingAs($minorUser)->post(
            "/api/v1/households/{$household->id}/trips/{$trip->id}/thumbnail",
            ['thumbnail' => UploadedFile::fake()->image('cover.jpg')],
        )->assertForbidden();

        $response = $this->actingAs($owner)->post(
            "/api/v1/households/{$household->id}/trips/{$trip->id}/thumbnail",
            ['thumbnail' => UploadedFile::fake()->image('cover.jpg')],
        );

        $response->assertOk()
            ->assertJsonPath('data.thumbnail_url', fn ($url) => str_contains($url, '/media/trip-thumbnails/'));

        Storage::disk('public')->assertExists($trip->fresh()->thumbnail_path);
    }

    public function test_replacing_a_thumbnail_deletes_the_previous_file(): void
    {
        Storage::fake('public');

        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $ownerMember = $this->memberFor($owner, $household, HouseholdRole::Owner);

        $trip = Trip::factory()->create([
            'household_id' => $household->id,
            'created_by_member_id' => $ownerMember->id,
        ]);

        $this->actingAs($owner)->post(
            "/api/v1/households/{$household->id}/trips/{$trip->id}/thumbnail",
            ['thumbnail' => UploadedFile::fake()->image('first.jpg')],
        );
        $firstPath = $trip->fresh()->thumbnail_path;

        $this->actingAs($owner)->post(
            "/api/v1/households/{$household->id}/trips/{$trip->id}/thumbnail",
            ['thumbnail' => UploadedFile::fake()->image('second.jpg')],
        );
        $secondPath = $trip->fresh()->thumbnail_path;

        $this->assertNotSame($firstPath, $secondPath);
        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($secondPath);
    }
}
