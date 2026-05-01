<?php

namespace Tests\Feature\Infrastructure\Persistence\Eloquent\Repositories;

use App\Application\ServiceOrderService\DTOs\CreateServiceOrderServiceDTO;
use App\Infrastructure\Persistence\Eloquent\Repositories\ServiceOrderServiceRepositoryEloquent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ServiceOrderServiceRepositoryEloquentTest extends TestCase
{
    use RefreshDatabase;

    private ServiceOrderServiceRepositoryEloquent $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new ServiceOrderServiceRepositoryEloquent();
    }

    private function seedDependencies(): array
    {
        $user = User::factory()->create();

        $customerId = Str::uuid()->toString();
        DB::table('customers')->insert([
            'id' => $customerId,
            'name' => 'Cliente Teste',
            'email' => 'cliente@example.com',
            'phone' => '11999990000',
            'document' => '52998224725',
            'created_user_id' => $user->id,
            'updated_user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $vehicleId = Str::uuid()->toString();
        DB::table('vehicles')->insert([
            'id' => $vehicleId,
            'brand' => 'Toyota',
            'model' => 'Corolla',
            'year' => 2020,
            'plate' => 'ABC1D23',
            'created_user_id' => $user->id,
            'updated_user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $serviceId = Str::uuid()->toString();
        DB::table('services')->insert([
            'id' => $serviceId,
            'name' => 'Troca de oleo',
            'price' => 199.9,
            'created_user_id' => $user->id,
            'updated_user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orderId = Str::uuid()->toString();
        DB::table('service_orders')->insert([
            'id' => $orderId,
            'customer_id' => $customerId,
            'vehicle_id' => $vehicleId,
            'customer_document' => '52998224725',
            'status' => 'recebida',
            'services_total' => 0,
            'parts_total' => 0,
            'total_budget' => 0,
            'created_user_id' => $user->id,
            'updated_user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$orderId, $serviceId];
    }

    public function test_create_service_order_service_persists_and_returns_entity(): void
    {
        [$orderId, $serviceId] = $this->seedDependencies();

        $result = $this->repository->createServiceOrderService(
            new CreateServiceOrderServiceDTO(
                service_order_id: $orderId,
                service_id: $serviceId,
                quantity: 2,
                price: 99.9,
                started_at: null,
                finished_at: null
            )
        );

        $persistedId = DB::table('service_order_services')
            ->where('service_order_id', $orderId)
            ->where('service_id', $serviceId)
            ->value('id');

        $this->assertNotEmpty($persistedId);
        $this->assertSame($orderId, $result->service_order_id);
        $this->assertSame($serviceId, $result->service_id);
        $this->assertSame(2, $result->quantity);
        $this->assertSame(99.9, $result->price);

        $this->assertDatabaseHas('service_order_services', [
            'id' => $persistedId,
            'service_order_id' => $orderId,
            'service_id' => $serviceId,
            'quantity' => 2,
        ]);
    }

    public function test_start_service_sets_started_at(): void
    {
        [$orderId, $serviceId] = $this->seedDependencies();

        $this->repository->createServiceOrderService(
            new CreateServiceOrderServiceDTO($orderId, $serviceId, 1, 50.0)
        );

        $persistedId = DB::table('service_order_services')
            ->where('service_order_id', $orderId)
            ->where('service_id', $serviceId)
            ->value('id');

        $started = $this->repository->startService((string) $persistedId);

        $this->assertNotNull($started->started_at);
        $this->assertNotNull(DB::table('service_order_services')->where('id', $persistedId)->value('started_at'));
    }

    public function test_finish_service_sets_finished_at(): void
    {
        [$orderId, $serviceId] = $this->seedDependencies();

        $this->repository->createServiceOrderService(
            new CreateServiceOrderServiceDTO($orderId, $serviceId, 1, 50.0)
        );

        $persistedId = DB::table('service_order_services')
            ->where('service_order_id', $orderId)
            ->where('service_id', $serviceId)
            ->value('id');

        $finished = $this->repository->finishService((string) $persistedId);

        $this->assertNotNull($finished->finished_at);
        $this->assertNotNull(DB::table('service_order_services')->where('id', $persistedId)->value('finished_at'));
    }

    public function test_start_service_throws_when_not_found(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Servico da OS nao encontrado.');

        $this->repository->startService(Str::uuid()->toString());
    }

    public function test_finish_service_throws_when_not_found(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Servico da OS nao encontrado.');

        $this->repository->finishService(Str::uuid()->toString());
    }
}
