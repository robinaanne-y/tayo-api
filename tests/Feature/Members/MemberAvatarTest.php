<?php

namespace Tests\Feature\Members;

use App\Enums\HouseholdRole;
use App\Models\Household;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MemberAvatarTest extends TestCase
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

    public function test_an_owner_can_upload_a_members_avatar(): void
    {
        Storage::fake('public');

        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $this->memberFor($owner, $household, HouseholdRole::Owner);
        $child = Member::factory()->create();
        $household->memberships()->create(['member_id' => $child->id, 'role' => HouseholdRole::Child]);

        $response = $this->actingAs($owner)->post(
            "/api/v1/households/{$household->id}/members/{$child->id}/avatar",
            ['avatar' => UploadedFile::fake()->image('avatar.jpg')],
        );

        $response->assertOk()->assertJsonPath('data.avatar_url', fn ($url) => str_contains($url, '/storage/avatars/'));

        $path = $child->fresh()->avatar_path;
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_replacing_an_avatar_deletes_the_previous_file(): void
    {
        Storage::fake('public');

        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $this->memberFor($owner, $household, HouseholdRole::Owner);
        $child = Member::factory()->create();
        $household->memberships()->create(['member_id' => $child->id, 'role' => HouseholdRole::Child]);

        $this->actingAs($owner)->post(
            "/api/v1/households/{$household->id}/members/{$child->id}/avatar",
            ['avatar' => UploadedFile::fake()->image('first.jpg')],
        );
        $firstPath = $child->fresh()->avatar_path;

        $this->actingAs($owner)->post(
            "/api/v1/households/{$household->id}/members/{$child->id}/avatar",
            ['avatar' => UploadedFile::fake()->image('second.jpg')],
        );
        $secondPath = $child->fresh()->avatar_path;

        $this->assertNotSame($firstPath, $secondPath);
        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($secondPath);
    }

    public function test_a_minor_cannot_upload_an_avatar(): void
    {
        Storage::fake('public');

        $owner = User::factory()->create();
        $minor = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $this->memberFor($owner, $household, HouseholdRole::Owner);
        $this->memberFor($minor, $household, HouseholdRole::Minor);
        $child = Member::factory()->create();
        $household->memberships()->create(['member_id' => $child->id, 'role' => HouseholdRole::Child]);

        $this->actingAs($minor)->post(
            "/api/v1/households/{$household->id}/members/{$child->id}/avatar",
            ['avatar' => UploadedFile::fake()->image('avatar.jpg')],
        )->assertForbidden();
    }

    public function test_the_file_must_be_an_image(): void
    {
        Storage::fake('public');

        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $this->memberFor($owner, $household, HouseholdRole::Owner);
        $child = Member::factory()->create();
        $household->memberships()->create(['member_id' => $child->id, 'role' => HouseholdRole::Child]);

        $this->actingAs($owner)->post(
            "/api/v1/households/{$household->id}/members/{$child->id}/avatar",
            ['avatar' => UploadedFile::fake()->create('notes.txt', 10)],
        )->assertUnprocessable()->assertJsonValidationErrors('avatar');
    }

    public function test_a_member_outside_the_household_returns_not_found(): void
    {
        Storage::fake('public');

        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $this->memberFor($owner, $household, HouseholdRole::Owner);
        $outsideMember = Member::factory()->create();

        $this->actingAs($owner)->post(
            "/api/v1/households/{$household->id}/members/{$outsideMember->id}/avatar",
            ['avatar' => UploadedFile::fake()->image('avatar.jpg')],
        )->assertNotFound();
    }
}
