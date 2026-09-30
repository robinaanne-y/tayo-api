<?php

namespace Tests\Feature\Invitations;

use App\Enums\HouseholdRole;
use App\Models\Household;
use App\Models\HouseholdInvitation;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HouseholdInvitationTest extends TestCase
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

    public function test_an_owner_can_create_an_invitation(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $this->memberFor($owner, $household, HouseholdRole::Owner);

        $response = $this->actingAs($owner)->postJson(
            "/api/v1/households/{$household->id}/invitations",
            ['role' => 'adult'],
        );

        $response->assertCreated()
            ->assertJsonPath('data.role', 'adult')
            ->assertJsonStructure(['data' => ['token', 'link', 'expires_at']]);

        $this->assertDatabaseCount('household_invitations', 1);
    }

    public function test_an_adult_can_create_an_invitation(): void
    {
        $owner = User::factory()->create();
        $adult = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $this->memberFor($owner, $household, HouseholdRole::Owner);
        $this->memberFor($adult, $household, HouseholdRole::Adult);

        $this->actingAs($adult)->postJson(
            "/api/v1/households/{$household->id}/invitations",
            ['role' => 'child'],
        )->assertCreated();
    }

    public function test_a_minor_cannot_create_an_invitation(): void
    {
        $owner = User::factory()->create();
        $minor = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $this->memberFor($owner, $household, HouseholdRole::Owner);
        $this->memberFor($minor, $household, HouseholdRole::Minor);

        $this->actingAs($minor)->postJson(
            "/api/v1/households/{$household->id}/invitations",
            ['role' => 'child'],
        )->assertForbidden();
    }

    public function test_an_invitation_cannot_grant_the_owner_role(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $this->memberFor($owner, $household, HouseholdRole::Owner);

        $this->actingAs($owner)->postJson(
            "/api/v1/households/{$household->id}/invitations",
            ['role' => 'owner'],
        )->assertUnprocessable()->assertJsonValidationErrors('role');
    }

    public function test_preview_returns_household_and_role_for_a_valid_token(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create([
            'created_by_user_id' => $owner->id,
            'name' => 'Santos Family',
        ]);
        $this->memberFor($owner, $household, HouseholdRole::Owner);

        $created = $this->actingAs($owner)->postJson(
            "/api/v1/households/{$household->id}/invitations",
            ['role' => 'adult'],
        )->json('data');

        $this->getJson("/api/v1/invitations/{$created['token']}")
            ->assertOk()
            ->assertJsonPath('data.household_name', 'Santos Family')
            ->assertJsonPath('data.role', 'adult')
            ->assertJsonPath('data.valid', true);
    }

    public function test_preview_reports_invalid_for_an_expired_token(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $member = $this->memberFor($owner, $household, HouseholdRole::Owner);

        $plain = HouseholdInvitation::newPlainToken();
        $household->invitations()->create([
            'created_by_member_id' => $member->id,
            'role' => HouseholdRole::Adult,
            'token_hash' => HouseholdInvitation::hashToken($plain),
            'expires_at' => now()->subDay(),
        ]);

        $this->getJson("/api/v1/invitations/{$plain}")
            ->assertOk()
            ->assertJsonPath('data.valid', false);
    }

    public function test_preview_404s_for_an_unknown_token(): void
    {
        $this->getJson('/api/v1/invitations/does-not-exist')->assertNotFound();
    }

    public function test_an_authenticated_user_can_accept_a_valid_invitation(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $this->memberFor($owner, $household, HouseholdRole::Owner);

        $created = $this->actingAs($owner)->postJson(
            "/api/v1/households/{$household->id}/invitations",
            ['role' => 'adult'],
        )->json('data');

        $invitee = User::factory()->create();

        $this->actingAs($invitee)->postJson("/api/v1/invitations/{$created['token']}/accept")
            ->assertOk();

        $this->assertDatabaseHas('household_memberships', [
            'household_id' => $household->id,
            'role' => 'adult',
        ]);
        $member = Member::query()->where('user_id', $invitee->id)->first();
        $this->assertNotNull($member);
        $this->assertTrue($household->memberships()->where('member_id', $member->id)->exists());
    }

    public function test_accepting_an_expired_invitation_fails(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $member = $this->memberFor($owner, $household, HouseholdRole::Owner);

        $plain = HouseholdInvitation::newPlainToken();
        $household->invitations()->create([
            'created_by_member_id' => $member->id,
            'role' => HouseholdRole::Adult,
            'token_hash' => HouseholdInvitation::hashToken($plain),
            'expires_at' => now()->subDay(),
        ]);

        $invitee = User::factory()->create();

        $this->actingAs($invitee)->postJson("/api/v1/invitations/{$plain}/accept")
            ->assertUnprocessable();
    }

    public function test_accepting_an_already_used_invitation_fails(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $this->memberFor($owner, $household, HouseholdRole::Owner);

        $created = $this->actingAs($owner)->postJson(
            "/api/v1/households/{$household->id}/invitations",
            ['role' => 'adult'],
        )->json('data');

        $first = User::factory()->create();
        $this->actingAs($first)->postJson("/api/v1/invitations/{$created['token']}/accept")
            ->assertOk();

        $second = User::factory()->create();
        $this->actingAs($second)->postJson("/api/v1/invitations/{$created['token']}/accept")
            ->assertUnprocessable();
    }

    public function test_a_member_of_the_household_cannot_accept_again(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $this->memberFor($owner, $household, HouseholdRole::Owner);

        $created = $this->actingAs($owner)->postJson(
            "/api/v1/households/{$household->id}/invitations",
            ['role' => 'adult'],
        )->json('data');

        $this->actingAs($owner)->postJson("/api/v1/invitations/{$created['token']}/accept")
            ->assertUnprocessable();
    }
}
