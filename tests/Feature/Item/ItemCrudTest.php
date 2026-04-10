<?php

namespace Tests\Feature\Item;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemCrudTest extends TestCase
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
            'name'             => 'Brake Pad',
            'code'             => 'BP-001',
            'type'             => 'part',
            'measure_unit'     => 'un',
            'minimum_quantity' => 2,
            'description'      => 'Front brake pad',
            'unit_price'       => 49.90,
        ], $overrides);
    }

    // -------------------------------------------------------------------------
    // POST /api/item
    // -------------------------------------------------------------------------

    public function test_store_creates_item_and_returns_201(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/item', $this->validPayload());

        $response->assertStatus(201)
            ->assertJsonStructure(['id', 'message']);

        $this->assertDatabaseHas('items', [
            'code' => 'BP-001',
            'name' => 'Brake Pad',
            'type' => 'part',
        ]);
    }

    public function test_store_returns_401_without_authentication(): void
    {
        $this->postJson('/api/item', $this->validPayload())
            ->assertStatus(401);
    }

    public function test_store_returns_422_when_code_already_exists(): void
    {
        $this->actingAs($this->user, 'api')
            ->postJson('/api/item', $this->validPayload());

        $this->actingAs($this->user, 'api')
            ->postJson('/api/item', $this->validPayload(['name' => 'Other Item']))
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'An item with this code already exists.']);
    }

    public function test_store_returns_422_for_missing_required_fields(): void
    {
        $this->actingAs($this->user, 'api')
            ->postJson('/api/item', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'code', 'type', 'measure_unit', 'minimum_quantity']);
    }

    public function test_store_returns_422_for_invalid_type(): void
    {
        $this->actingAs($this->user, 'api')
            ->postJson('/api/item', $this->validPayload(['type' => 'invalid']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }

    public function test_store_returns_422_for_invalid_measure_unit(): void
    {
        $this->actingAs($this->user, 'api')
            ->postJson('/api/item', $this->validPayload(['measure_unit' => 'oz']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['measure_unit']);
    }

    public function test_store_initial_stock_is_zero(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/item', $this->validPayload());

        $id = $response->json('id');

        $this->getJson("/api/item/{$id}")
            ->assertOk()
            ->assertJsonFragment(['stock_quantity' => 0]);
    }

    // -------------------------------------------------------------------------
    // GET /api/item
    // -------------------------------------------------------------------------

    public function test_list_returns_200_with_items(): void
    {
        $this->actingAs($this->user, 'api')->postJson('/api/item', $this->validPayload());
        $this->actingAs($this->user, 'api')->postJson('/api/item', $this->validPayload([
            'code' => 'BP-002',
            'name' => 'Oil Filter',
        ]));

        $this->actingAs($this->user, 'api')
            ->getJson('/api/item')
            ->assertOk()
            ->assertJsonStructure(['data', 'total', 'page', 'perPage']);
    }

    public function test_list_returns_401_without_authentication(): void
    {
        $this->getJson('/api/item')->assertStatus(401);
    }

    public function test_list_filters_by_type(): void
    {
        $this->actingAs($this->user, 'api')->postJson('/api/item', $this->validPayload([
            'code' => 'PART-001',
            'type' => 'part',
        ]));
        $this->actingAs($this->user, 'api')->postJson('/api/item', $this->validPayload([
            'code' => 'SUPP-001',
            'type' => 'supply',
        ]));

        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/item?type=supply');

        $response->assertOk();

        $items = $response->json('data');
        $this->assertCount(1, $items);
        $this->assertSame('supply', $items[0]['type']);
    }

    public function test_list_returns_paginated_response(): void
    {
        for ($i = 1; $i <= 3; $i++) {
            $this->actingAs($this->user, 'api')->postJson('/api/item', $this->validPayload([
                'code' => "ITEM-00{$i}",
                'name' => "Item {$i}",
            ]));
        }

        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/item?page=1&perPage=2');

        $response->assertOk()
            ->assertJsonStructure(['data', 'total', 'page', 'perPage']);

        $this->assertSame(1, $response->json('page'));
        $this->assertCount(2, $response->json('data'));
        $this->assertSame(3, $response->json('total'));
    }

    // -------------------------------------------------------------------------
    // GET /api/item/{id}
    // -------------------------------------------------------------------------

    public function test_show_returns_item_details(): void
    {
        $id = $this->actingAs($this->user, 'api')
            ->postJson('/api/item', $this->validPayload())
            ->json('id');

        $this->actingAs($this->user, 'api')
            ->getJson("/api/item/{$id}")
            ->assertOk()
            ->assertJsonStructure([
                'id', 'name', 'code', 'type', 'description',
                'measure_unit', 'stock_quantity', 'minimum_quantity',
                'unit_price', 'is_low_stock',
            ])
            ->assertJsonFragment([
                'code'         => 'BP-001',
                'name'         => 'Brake Pad',
                'type'         => 'part',
                'measure_unit' => 'un',
            ]);
    }

    public function test_show_returns_422_for_nonexistent_item(): void
    {
        $this->actingAs($this->user, 'api')
            ->getJson('/api/item/00000000-0000-0000-0000-000000000000')
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Item not found.']);
    }

    // -------------------------------------------------------------------------
    // PUT /api/item/{id}
    // -------------------------------------------------------------------------

    public function test_update_modifies_item_fields(): void
    {
        $id = $this->actingAs($this->user, 'api')
            ->postJson('/api/item', $this->validPayload())
            ->json('id');

        $this->actingAs($this->user, 'api')
            ->putJson("/api/item/{$id}", ['name' => 'Updated Pad', 'unit_price' => 59.90])
            ->assertOk()
            ->assertJsonPath('item.name', 'Updated Pad')
            ->assertJsonPath('item.unit_price', 59.90);
    }

    public function test_update_returns_422_for_duplicate_code(): void
    {
        $this->actingAs($this->user, 'api')
            ->postJson('/api/item', $this->validPayload(['code' => 'FIRST-01']));

        $secondId = $this->actingAs($this->user, 'api')
            ->postJson('/api/item', $this->validPayload(['code' => 'SCND-01']))
            ->json('id');

        $this->actingAs($this->user, 'api')
            ->putJson("/api/item/{$secondId}", ['code' => 'FIRST-01'])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Another item already uses this code.']);
    }

    public function test_update_returns_422_for_nonexistent_item(): void
    {
        $this->actingAs($this->user, 'api')
            ->putJson('/api/item/00000000-0000-0000-0000-000000000000', ['name' => 'X'])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Item not found.']);
    }

    public function test_update_returns_401_without_authentication(): void
    {
        $this->putJson('/api/item/00000000-0000-0000-0000-000000000000', ['name' => 'X'])
            ->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // DELETE /api/item/{id}
    // -------------------------------------------------------------------------

    public function test_destroy_removes_item_with_zero_stock(): void
    {
        $id = $this->actingAs($this->user, 'api')
            ->postJson('/api/item', $this->validPayload())
            ->json('id');

        $this->actingAs($this->user, 'api')
            ->deleteJson("/api/item/{$id}")
            ->assertOk()
            ->assertJsonFragment(['message' => 'Item removed successfully.']);

        $this->assertDatabaseMissing('items', ['id' => $id]);
    }

    public function test_destroy_returns_422_when_item_has_stock(): void
    {
        $id = $this->actingAs($this->user, 'api')
            ->postJson('/api/item', $this->validPayload())
            ->json('id');

        $this->actingAs($this->user, 'api')
            ->postJson("/api/item/{$id}/stock/entry", [
                'quantity' => 10,
                'reason'   => 'Initial entry',
            ]);

        $this->actingAs($this->user, 'api')
            ->deleteJson("/api/item/{$id}")
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Cannot delete an item with available stock.']);
    }

    public function test_destroy_returns_422_for_nonexistent_item(): void
    {
        $this->actingAs($this->user, 'api')
            ->deleteJson('/api/item/00000000-0000-0000-0000-000000000000')
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Item not found.']);
    }

    public function test_destroy_returns_401_without_authentication(): void
    {
        $this->deleteJson('/api/item/00000000-0000-0000-0000-000000000000')
            ->assertStatus(401);
    }
}
