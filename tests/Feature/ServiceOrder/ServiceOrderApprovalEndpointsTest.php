<?php

namespace Tests\Feature\ServiceOrder;

use App\Domain\Notification\Interfaces\NotificationServiceInterface;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ServiceOrderApprovalEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $customerId;
    private string $vehicleId;

    private const VALID_CPF = '52998224725';

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(NotificationServiceInterface::class)
            ->shouldReceive('send')
            ->andReturn(null);

        $this->user = User::factory()->create(['document' => self::VALID_CPF]);

        $this->customerId = Str::uuid()->toString();
        DB::table('customers')->insert([
            'id' => $this->customerId,
            'name' => 'Cliente Aprovacao',
            'email' => 'cliente.aprovacao@example.com',
            'phone' => '11999990000',
            'document' => self::VALID_CPF,
            'created_user_id' => $this->user->id,
            'updated_user_id' => $this->user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $vehicleResponse = $this->actingAs($this->user, 'api')
            ->postJson('/api/vehicle', [
                'brand' => 'Toyota',
                'model' => 'Corolla',
                'year' => 2020,
                'plate' => 'ABC1D23',
            ])
            ->assertOk();

        $this->vehicleId = (string) $vehicleResponse->json('id');
    }

    private function createAwaitingApprovalOrder(string $token): string
    {
        $id = Str::uuid()->toString();

        DB::table('service_orders')->insert([
            'id' => $id,
            'customer_id' => $this->customerId,
            'vehicle_id' => $this->vehicleId,
            'status' => 'aguardando_aprovacao',
            'services_total' => 100.0,
            'parts_total' => 0.0,
            'total_budget' => 100.0,
            'quote_sent_at' => now(),
            'quote_approved_at' => null,
            'approval_token' => $token,
            'created_user_id' => $this->user->id,
            'updated_user_id' => $this->user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    public function test_public_get_approve_endpoint_approves_quote(): void
    {
        $token = bin2hex(random_bytes(16));
        $osId = $this->createAwaitingApprovalOrder($token);

        $this->getJson("/api/service-order/approve/{$token}")
            ->assertOk()
            ->assertJsonPath('service_order_id', $osId)
            ->assertJsonPath('status', 'em_execucao');

        $this->assertDatabaseHas('service_orders', [
            'id' => $osId,
            'status' => 'em_execucao',
            'approval_token' => null,
        ]);
    }

    public function test_public_get_reject_endpoint_rejects_quote(): void
    {
        $token = bin2hex(random_bytes(16));
        $osId = $this->createAwaitingApprovalOrder($token);

        $this->getJson("/api/service-order/reject/{$token}")
            ->assertOk()
            ->assertJsonPath('service_order_id', $osId)
            ->assertJsonPath('status', 'em_diagnostico');

        $this->assertDatabaseHas('service_orders', [
            'id' => $osId,
            'status' => 'em_diagnostico',
            'approval_token' => null,
        ]);
    }

    public function test_public_post_approval_endpoint_processes_decision(): void
    {
        $approveToken = bin2hex(random_bytes(16));
        $rejectToken = bin2hex(random_bytes(16));
        $approveOsId = $this->createAwaitingApprovalOrder($approveToken);
        $rejectOsId = $this->createAwaitingApprovalOrder($rejectToken);

        $this->postJson("/api/service-order/approval/{$approveToken}", [
            'decision' => 'approve',
        ])
            ->assertOk()
            ->assertJsonPath('service_order_id', $approveOsId)
            ->assertJsonPath('status', 'em_execucao');

        $this->postJson("/api/service-order/approval/{$rejectToken}", [
            'decision' => 'reject',
        ])
            ->assertOk()
            ->assertJsonPath('service_order_id', $rejectOsId)
            ->assertJsonPath('status', 'em_diagnostico');
    }
}
