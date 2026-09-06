<?php

namespace Tests\Feature\Activation;

use App\Enums\HouseholdRole;
use App\Models\Household;
use App\Models\Member;
use App\Models\MemberActivationToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberActivationTest extends TestCase
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

    private function placeholderFor(Household $household, HouseholdRole $role, string $name = 'Ben'): Member
    {
        $member = Member::factory()->create(['user_id' => null, 'name' => $name]);

        $household->memberships()->create([
            'member_id' => $member->id,
            'role' => $role,
        ]);

        return $member;
    }

    public function test_an_owner_can_generate_an_activation_link_for_a_placeholder(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $this->memberFor($owner, $household, HouseholdRole::Owner);
        $child = $this->placeholderFor($household, HouseholdRole::Child);

        $response = $this->actingAs($owner)->postJson(
            "/api/v1/households/{$household->id}/members/{$child->id}/activation-link",
        );

        $response->assertCreated()
            ->assertJsonStructure(['data' => ['token', 'link', 'expires_at']]);

        $this->assertDatabaseCount('member_activation_tokens', 1);
    }

    public function test_a_minor_cannot_generate_an_activation_link(): void
    {
        $owner = User::factory()->create();
        $minor = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $this->memberFor($owner, $household, HouseholdRole::Owner);
        $this->memberFor($minor, $household, HouseholdRole::Minor);
        $child = $this->placeholderFor($household, HouseholdRole::Child);

        $this->actingAs($minor)->postJson(
            "/api/v1/households/{$household->id}/members/{$child->id}/activation-link",
        )->assertForbidden();
    }

    public function test_cannot_generate_a_link_for_a_member_who_already_has_an_account(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $this->memberFor($owner, $household, HouseholdRole::Owner);
        $adult = $this->memberFor(User::factory()->create(), $household, HouseholdRole::Adult);

        $this->actingAs($owner)->postJson(
            "/api/v1/households/{$household->id}/members/{$adult->id}/activation-link",
        )->assertUnprocessable();
    }

    public function test_cannot_generate_a_link_for_a_member_outside_the_household(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $this->memberFor($owner, $household, HouseholdRole::Owner);

        $otherHousehold = Household::factory()->create();
        $outsideChild = $this->placeholderFor($otherHousehold, HouseholdRole::Child);

        $this->actingAs($owner)->postJson(
            "/api/v1/households/{$household->id}/members/{$outsideChild->id}/activation-link",
        )->assertNotFound();
    }

    public function test_regenerating_invalidates_the_previous_token(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $this->memberFor($owner, $household, HouseholdRole::Owner);
        $child = $this->placeholderFor($household, HouseholdRole::Child);

        $first = $this->actingAs($owner)->postJson(
            "/api/v1/households/{$household->id}/members/{$child->id}/activation-link",
        )->json('data');

        $this->actingAs($owner)->postJson(
            "/api/v1/households/{$household->id}/members/{$child->id}/activation-link",
        )->assertCreated();

        $this->getJson("/api/v1/activation/{$first['token']}")
            ->assertJsonPath('data.valid', false);
    }

    public function test_preview_returns_member_and_household_name(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create([
            'created_by_user_id' => $owner->id,
            'name' => 'Santos Family',
        ]);
        $this->memberFor($owner, $household, HouseholdRole::Owner);
        $child = $this->placeholderFor($household, HouseholdRole::Child, 'Ben');

        $created = $this->actingAs($owner)->postJson(
            "/api/v1/households/{$household->id}/members/{$child->id}/activation-link",
        )->json('data');

        $this->getJson("/api/v1/activation/{$created['token']}")
            ->assertOk()
            ->assertJsonPath('data.member_name', 'Ben')
            ->assertJsonPath('data.household_name', 'Santos Family')
            ->assertJsonPath('data.valid', true);
    }

    public function test_claiming_a_valid_token_creates_an_account_and_links_the_member(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $this->memberFor($owner, $household, HouseholdRole::Owner);
        $child = $this->placeholderFor($household, HouseholdRole::Child, 'Ben');

        $created = $this->actingAs($owner)->postJson(
            "/api/v1/households/{$household->id}/members/{$child->id}/activation-link",
        )->json('data');

        $response = $this->postJson("/api/v1/activation/{$created['token']}/claim", [
            'email' => 'ben@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Ben')
            ->assertJsonStructure(['data', 'token']);

        $this->assertDatabaseHas('members', ['id' => $child->id, 'name' => 'Ben']);
        $child->refresh();
        $this->assertNotNull($child->user_id);
        $this->assertDatabaseHas('users', ['id' => $child->user_id, 'email' => 'ben@example.com']);
    }

    public function test_claiming_the_same_token_twice_fails(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $this->memberFor($owner, $household, HouseholdRole::Owner);
        $child = $this->placeholderFor($household, HouseholdRole::Child);

        $created = $this->actingAs($owner)->postJson(
            "/api/v1/households/{$household->id}/members/{$child->id}/activation-link",
        )->json('data');

        $this->postJson("/api/v1/activation/{$created['token']}/claim", [
            'email' => 'first@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();

        $this->postJson("/api/v1/activation/{$created['token']}/claim", [
            'email' => 'second@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertUnprocessable();
    }

    public function test_claiming_an_expired_token_fails(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $this->memberFor($owner, $household, HouseholdRole::Owner);
        $child = $this->placeholderFor($household, HouseholdRole::Child);

        $plain = MemberActivationToken::newPlainToken();
        $child->activationTokens()->create([
            'token_hash' => MemberActivationToken::hashToken($plain),
            'expires_at' => now()->subDay(),
        ]);

        $this->postJson("/api/v1/activation/{$plain}/claim", [
            'email' => 'ben@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertUnprocessable();
    }
}
