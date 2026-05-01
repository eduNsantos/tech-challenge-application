<?php

namespace Tests\Feature\Vehicle;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleCrudTest extends TestCase
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
            'brand' => 'Toyota',
            'model' => 'Corolla',
            'year' => 2020,
            'plate' => 'ABC1D23',
        ], $overrides);
    }

    public function test_store_creates_vehicle_and_returns_200(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/vehicle', $this->validPayload());

        $response->assertOk()
            ->assertJsonStructure(['id', 'message'])
            ->assertJsonFragment(['message' => 'Veículo cadastrado com sucesso']);

        $this->assertDatabaseHas('vehicles', [
            'id' => $response->json('id'),
            'plate' => 'ABC1D23',
        ]);
    }

    public function test_store_returns_401_without_authentication(): void
    {
        $this->postJson('/api/vehicle', $this->validPayload())
            ->assertStatus(401);
    }

    public function test_store_returns_422_for_missing_required_fields(): void
    {
        $this->actingAs($this->user, 'api')
            ->postJson('/api/vehicle', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['brand', 'model', 'year', 'plate']);
    }

    public function test_list_returns_vehicles_array(): void
    {
        $this->actingAs($this->user, 'api')->postJson('/api/vehicle', $this->validPayload());
        $this->actingAs($this->user, 'api')->postJson('/api/vehicle', $this->validPayload(['plate' => 'DEF2G34']));

        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/vehicle?page=1&perPage=1')
            ->assertOk();

        $data = $response->json();
        $this->assertIsArray($data);
        $this->assertNotEmpty($data);
        $this->assertArrayHasKey('id', $data[0]);
        $this->assertArrayHasKey('plate', $data[0]);
    }

    public function test_show_returns_vehicle_by_id(): void
    {
        $id = $this->actingAs($this->user, 'api')
            ->postJson('/api/vehicle', $this->validPayload(['plate' => 'GHI3J45']))
            ->json('id');

        $response = $this->actingAs($this->user, 'api')
            ->getJson("/api/vehicle/{$id}")
            ->assertOk();

        $response->assertJsonPath('id', $id)
            ->assertJsonPath('brand', 'Toyota')
            ->assertJsonPath('model', 'Corolla')
            ->assertJsonPath('year', 2020);
    }

    public function test_update_changes_vehicle_data(): void
    {
        $id = $this->actingAs($this->user, 'api')
            ->postJson('/api/vehicle', $this->validPayload(['plate' => 'JKL4M56']))
            ->json('id');

        $this->actingAs($this->user, 'api')
            ->putJson("/api/vehicle/{$id}", [
                'brand' => 'Honda',
                'model' => 'Civic',
                'year' => 2024,
                'plate' => 'NOP5Q67',
            ])
            ->assertOk()
            ->assertJsonPath('vehicle.brand', 'Honda')
            ->assertJsonPath('vehicle.model', 'Civic')
            ->assertJsonPath('vehicle.year', 2024)
            ->assertJsonPath('vehicle.plate', 'NOP5Q67');
    }

    public function test_update_returns_422_for_invalid_uuid_id(): void
    {
        $this->actingAs($this->user, 'api')
            ->putJson('/api/vehicle/not-uuid', [
                'brand' => 'Honda',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['id']);
    }
}
