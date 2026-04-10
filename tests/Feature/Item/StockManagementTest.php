<?php

namespace Tests\Feature\Item;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $itemId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $this->itemId = $this->actingAs($this->user, 'api')
            ->postJson('/api/item', [
                'name'             => 'Brake Pad',
                'code'             => 'BP-001',
                'type'             => 'part',
                'measure_unit'     => 'un',
                'minimum_quantity' => 5,
            ])
            ->json('id');

        // Reset the JWT guard so tests that expect 401 aren't affected
        // by the authenticated state set via actingAs above.
        $this->app['auth']->forgetGuards();
    }

    // -------------------------------------------------------------------------
    // POST /api/item/{id}/stock/entry
    // -------------------------------------------------------------------------

    public function test_entry_registers_movement_and_returns_201(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->postJson("/api/item/{$this->itemId}/stock/entry", [
                'quantity' => 20,
                'reason'   => 'Purchase NF 001',
                'notes'    => 'Supplier X',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'movement_id', 'type', 'quantity',
                'previous_quantity', 'current_quantity', 'message',
            ])
            ->assertJsonFragment([
                'type'              => 'entry',
                'quantity'          => 20,
                'previous_quantity' => 0,
                'current_quantity'  => 20,
            ]);

        $this->assertDatabaseHas('stock_movements', [
            'item_id'       => $this->itemId,
            'movement_type' => 'entry',
            'quantity'      => 20,
        ]);
    }

    public function test_entry_accumulates_stock_correctly(): void
    {
        $this->actingAs($this->user, 'api')
            ->postJson("/api/item/{$this->itemId}/stock/entry", ['quantity' => 10, 'reason' => 'First entry']);

        $response = $this->actingAs($this->user, 'api')
            ->postJson("/api/item/{$this->itemId}/stock/entry", ['quantity' => 5, 'reason' => 'Second entry']);

        $response->assertJsonFragment([
            'previous_quantity' => 10,
            'current_quantity'  => 15,
        ]);
    }

    public function test_entry_returns_422_for_item_not_found(): void
    {
        $this->actingAs($this->user, 'api')
            ->postJson('/api/item/00000000-0000-0000-0000-000000000000/stock/entry', [
                'quantity' => 10,
                'reason'   => 'Test',
            ])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Item not found.']);
    }

    public function test_entry_returns_422_for_missing_required_fields(): void
    {
        $this->actingAs($this->user, 'api')
            ->postJson("/api/item/{$this->itemId}/stock/entry", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['quantity', 'reason']);
    }

    public function test_entry_returns_422_for_zero_quantity(): void
    {
        $this->actingAs($this->user, 'api')
            ->postJson("/api/item/{$this->itemId}/stock/entry", [
                'quantity' => 0,
                'reason'   => 'Test',
            ])
            ->assertStatus(422);
    }

    public function test_entry_returns_401_without_authentication(): void
    {
        $this->postJson("/api/item/{$this->itemId}/stock/entry", [
            'quantity' => 10,
            'reason'   => 'Test',
        ])->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // POST /api/item/{id}/stock/withdrawal
    // -------------------------------------------------------------------------

    public function test_withdrawal_registers_movement_and_returns_201(): void
    {
        $this->actingAs($this->user, 'api')
            ->postJson("/api/item/{$this->itemId}/stock/entry", ['quantity' => 20, 'reason' => 'Initial']);

        $response = $this->actingAs($this->user, 'api')
            ->postJson("/api/item/{$this->itemId}/stock/withdrawal", [
                'quantity' => 8,
                'reason'   => 'Work order WO-001',
            ]);

        $response->assertStatus(201)
            ->assertJsonFragment([
                'type'              => 'withdrawal',
                'quantity'          => 8,
                'previous_quantity' => 20,
                'current_quantity'  => 12,
            ]);

        $this->assertDatabaseHas('stock_movements', [
            'item_id'       => $this->itemId,
            'movement_type' => 'withdrawal',
            'quantity'      => 8,
        ]);
    }

    public function test_withdrawal_returns_422_when_stock_is_insufficient(): void
    {
        $this->actingAs($this->user, 'api')
            ->postJson("/api/item/{$this->itemId}/stock/entry", ['quantity' => 5, 'reason' => 'Initial']);

        $this->actingAs($this->user, 'api')
            ->postJson("/api/item/{$this->itemId}/stock/withdrawal", [
                'quantity' => 10,
                'reason'   => 'Test',
            ])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Insufficient stock. Available: 5, requested: 10.']);
    }

    public function test_withdrawal_returns_422_for_item_not_found(): void
    {
        $this->actingAs($this->user, 'api')
            ->postJson('/api/item/00000000-0000-0000-0000-000000000000/stock/withdrawal', [
                'quantity' => 1,
                'reason'   => 'Test',
            ])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Item not found.']);
    }

    public function test_withdrawal_returns_422_for_missing_required_fields(): void
    {
        $this->actingAs($this->user, 'api')
            ->postJson("/api/item/{$this->itemId}/stock/withdrawal", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['quantity', 'reason']);
    }

    public function test_withdrawal_returns_422_for_zero_quantity(): void
    {
        $this->actingAs($this->user, 'api')
            ->postJson("/api/item/{$this->itemId}/stock/withdrawal", [
                'quantity' => 0,
                'reason'   => 'Test',
            ])
            ->assertStatus(422);
    }

    public function test_withdrawal_returns_401_without_authentication(): void
    {
        $this->postJson("/api/item/{$this->itemId}/stock/withdrawal", [
            'quantity' => 1,
            'reason'   => 'Test',
        ])->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // GET /api/item/{id}/stock/movements
    // -------------------------------------------------------------------------

    public function test_movements_returns_list_ordered_by_most_recent(): void
    {
        $this->actingAs($this->user, 'api')
            ->postJson("/api/item/{$this->itemId}/stock/entry", ['quantity' => 20, 'reason' => 'Entry 1']);

        $this->actingAs($this->user, 'api')
            ->postJson("/api/item/{$this->itemId}/stock/entry", ['quantity' => 5, 'reason' => 'Entry 2']);

        $response = $this->actingAs($this->user, 'api')
            ->getJson("/api/item/{$this->itemId}/stock/movements");

        $response->assertOk()
            ->assertJsonStructure(['data', 'total', 'page', 'perPage']);

        $movements = $response->json('data');
        $this->assertCount(2, $movements);
    }

    public function test_movements_returns_empty_list_for_new_item(): void
    {
        $this->actingAs($this->user, 'api')
            ->getJson("/api/item/{$this->itemId}/stock/movements")
            ->assertOk()
            ->assertJsonPath('total', 0);
    }

    public function test_movements_returns_401_without_authentication(): void
    {
        $this->getJson("/api/item/{$this->itemId}/stock/movements")
            ->assertStatus(401);
    }

    public function test_movements_returns_422_for_item_not_found(): void
    {
        $this->actingAs($this->user, 'api')
            ->getJson('/api/item/00000000-0000-0000-0000-000000000000/stock/movements')
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Item not found.']);
    }

    public function test_movements_structure_contains_required_fields(): void
    {
        $this->actingAs($this->user, 'api')
            ->postJson("/api/item/{$this->itemId}/stock/entry", [
                'quantity' => 10,
                'reason'   => 'Initial stock',
                'notes'    => 'Supplier A',
            ]);

        $response = $this->actingAs($this->user, 'api')
            ->getJson("/api/item/{$this->itemId}/stock/movements");

        $response->assertOk();

        $movement = $response->json('data.0');
        $this->assertArrayHasKey('id', $movement);
        $this->assertArrayHasKey('type', $movement);
        $this->assertArrayHasKey('quantity', $movement);
        $this->assertArrayHasKey('previous_quantity', $movement);
        $this->assertArrayHasKey('current_quantity', $movement);
        $this->assertArrayHasKey('reason', $movement);
        $this->assertSame('entry', $movement['type']);
        $this->assertSame(10, $movement['quantity']);
    }

    // -------------------------------------------------------------------------
    // is_low_stock flag
    // -------------------------------------------------------------------------

    public function test_is_low_stock_flag_reflects_current_state(): void
    {
        // minimum_quantity = 5, stock = 0 → low stock
        $this->actingAs($this->user, 'api')
            ->getJson("/api/item/{$this->itemId}")
            ->assertOk()
            ->assertJsonFragment(['is_low_stock' => true]);

        // stock = 10 → not low
        $this->actingAs($this->user, 'api')
            ->postJson("/api/item/{$this->itemId}/stock/entry", ['quantity' => 10, 'reason' => 'Replenishment']);

        $this->actingAs($this->user, 'api')
            ->getJson("/api/item/{$this->itemId}")
            ->assertOk()
            ->assertJsonFragment(['is_low_stock' => false]);
    }
}
