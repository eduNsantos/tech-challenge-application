<?php

namespace Tests\Feature\ServiceOrder;

use App\Domain\Notification\Interfaces\NotificationServiceInterface;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ListServiceOrderTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $customerId;
    private string $vehicleId;
    private string $serviceId;

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
            'id'              => $this->customerId,
            'name'            => 'Cliente Teste',
            'email'           => 'cliente@example.com',
            'phone'           => '11999990000',
            'document'        => self::VALID_CPF,
            'created_user_id' => $this->user->id,
            'updated_user_id' => $this->user->id,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $vehicleResponse = $this->actingAs($this->user, 'api')
            ->postJson('/api/vehicle', [
                'brand' => 'Toyota',
                'model' => 'Corolla',
                'year'  => 2020,
                'plate' => 'ABC1D23',
            ]);
        $this->vehicleId = $vehicleResponse->json('id');

        $this->serviceId = $this->actingAs($this->user, 'api')
            ->postJson('/api/service', ['name' => 'Alinhamento', 'price' => 100.0])
            ->json('id');

        $this->app['auth']->forgetGuards();
    }

    private function createOS(string $status, string $createdAt): string
    {
        $id = Str::uuid()->toString();
        DB::table('service_orders')->insert([
            'id'              => $id,
            'customer_id'     => $this->customerId,
            'vehicle_id'      => $this->vehicleId,
            'status'          => $status,
            'services_total'  => 100.0,
            'parts_total'     => 0.0,
            'total_budget'    => 100.0,
            'quote_sent_at'   => null,
            'quote_approved_at' => null,
            'approval_token'  => null,
            'created_user_id' => $this->user->id,
            'updated_user_id' => $this->user->id,
            'created_at'      => $createdAt,
            'updated_at'      => $createdAt,
        ]);
        return $id;
    }

    public function test_list_excludes_finalizada_and_entregue(): void
    {
        $this->createOS('recebida', now()->subMinutes(4)->toDateTimeString());
        $this->createOS('finalizada', now()->subMinutes(3)->toDateTimeString());
        $this->createOS('entregue', now()->subMinutes(2)->toDateTimeString());

        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/service-order')
            ->assertOk();

        $statuses = collect($response->json())->pluck('status')->all();

        $this->assertCount(1, $statuses);
        $this->assertNotContains('finalizada', $statuses);
        $this->assertNotContains('entregue', $statuses);
    }

    public function test_list_ordering_by_priority_then_oldest_first(): void
    {
        // Cria OS em ordem inversa à esperada para validar que a ordenação funciona
        $idEntregue       = $this->createOS('entregue',              now()->subMinutes(10)->toDateTimeString());
        $idRecebida1      = $this->createOS('recebida',              now()->subMinutes(5)->toDateTimeString());
        $idRecebida2      = $this->createOS('recebida',              now()->subMinutes(4)->toDateTimeString());
        $idDiagnostico    = $this->createOS('em_diagnostico',        now()->subMinutes(3)->toDateTimeString());
        $idAguardando     = $this->createOS('aguardando_aprovacao',  now()->subMinutes(2)->toDateTimeString());
        $idEmExecucao     = $this->createOS('em_execucao',           now()->subMinutes(1)->toDateTimeString());

        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/service-order')
            ->assertOk();

        $ids = collect($response->json())->pluck('id')->all();

        $this->assertSame([
            $idEmExecucao,
            $idAguardando,
            $idDiagnostico,
            $idRecebida1,
            $idRecebida2,
        ], $ids);
    }

    public function test_list_returns_empty_when_all_are_finalizada_or_entregue(): void
    {
        $this->createOS('finalizada', now()->toDateTimeString());
        $this->createOS('entregue',   now()->toDateTimeString());

        $this->actingAs($this->user, 'api')
            ->getJson('/api/service-order')
            ->assertOk()
            ->assertExactJson([]);
    }
}
