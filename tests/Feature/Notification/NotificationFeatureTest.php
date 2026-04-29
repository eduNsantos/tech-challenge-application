<?php

namespace Tests\Feature\Notification;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationFeatureTest extends TestCase
{
    use RefreshDatabase;

    private const VALID_CPF = '52998224725';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['document' => self::VALID_CPF]);
    }

    // -------------------------------------------------------------------------
    // GET /api/notification
    // -------------------------------------------------------------------------

    public function test_list_returns_401_without_authentication(): void
    {
        $this->getJson('/api/notification')
            ->assertStatus(401);
    }

    public function test_list_returns_200_when_authenticated(): void
    {
        $this->actingAs($this->user, 'api')
            ->getJson('/api/notification')
            ->assertStatus(200);
    }

    // -------------------------------------------------------------------------
    // GET /api/notification/{id}
    // -------------------------------------------------------------------------

    public function test_show_returns_401_without_authentication(): void
    {
        $this->getJson('/api/notification/some-uuid')
            ->assertStatus(401);
    }

    public function test_show_returns_500_for_unknown_id(): void
    {
        $this->actingAs($this->user, 'api')
            ->getJson('/api/notification/non-existent-uuid')
            ->assertStatus(500);
    }
}
