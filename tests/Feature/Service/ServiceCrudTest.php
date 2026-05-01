<?php

namespace Tests\Feature\Service;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Troca de oleo',
            'price' => 199.90,
        ], $overrides);
    }

    public function test_store_creates_service_and_returns_200(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/service', $this->validPayload());

        $response->assertOk()
            ->assertJsonStructure(['id', 'message'])
            ->assertJsonFragment(['message' => 'Serviço cadastrado com sucesso']);

        $this->assertDatabaseHas('services', [
            'id' => $response->json('id'),
            'name' => 'Troca de oleo',
        ]);
    }

    public function test_store_returns_400_when_name_already_exists(): void
    {
        $this->actingAs($this->user, 'api')->postJson('/api/service', $this->validPayload());

        $this->actingAs($this->user, 'api')
            ->postJson('/api/service', $this->validPayload())
            ->assertStatus(400)
            ->assertJsonFragment(['error' => 'Serviço já cadastrado']);
    }

    public function test_list_returns_services_array(): void
    {
        $this->actingAs($this->user, 'api')->postJson('/api/service', $this->validPayload(['name' => 'A']));
        $this->actingAs($this->user, 'api')->postJson('/api/service', $this->validPayload(['name' => 'B']));

        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/service?page=1&per_page=1')
            ->assertOk();

        $data = $response->json();
        $this->assertIsArray($data);
        $this->assertNotEmpty($data);
        $this->assertArrayHasKey('id', $data[0]);
        $this->assertArrayHasKey('name', $data[0]);
    }

    public function test_show_returns_service_by_id(): void
    {
        $id = $this->actingAs($this->user, 'api')
            ->postJson('/api/service', $this->validPayload(['name' => 'Balanceamento']))
            ->json('id');

        $this->actingAs($this->user, 'api')
            ->getJson("/api/service/{$id}")
            ->assertOk()
            ->assertJsonFragment(['id' => $id, 'name' => 'Balanceamento']);
    }

    public function test_update_changes_service_data(): void
    {
        $id = $this->actingAs($this->user, 'api')
            ->postJson('/api/service', $this->validPayload(['name' => 'Alinhamento']))
            ->json('id');

        $this->actingAs($this->user, 'api')
            ->putJson("/api/service/{$id}", [
                'name' => 'Alinhamento 3D',
                'price' => 250.00,
            ])
            ->assertOk()
            ->assertJsonPath('service.name', 'Alinhamento 3D')
            ->assertJsonPath('service.price', 250);
    }

    public function test_update_returns_422_for_invalid_uuid_id(): void
    {
        $this->actingAs($this->user, 'api')
            ->putJson('/api/service/not-uuid', [
                'name' => 'X',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['id']);
    }

    public function test_routes_require_authentication(): void
    {
        $this->getJson('/api/service')->assertStatus(401);
        $this->postJson('/api/service', $this->validPayload())->assertStatus(401);
        $this->getJson('/api/service/00000000-0000-0000-0000-000000000000')->assertStatus(401);
    }
}
