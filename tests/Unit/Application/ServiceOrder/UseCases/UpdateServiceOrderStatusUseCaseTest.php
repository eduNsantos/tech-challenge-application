<?php

namespace Tests\Unit\Application\ServiceOrder\UseCases;

use App\Application\ServiceOrder\DTOs\UpdateServiceOrderStatusDTO;
use App\Application\ServiceOrder\UseCases\UpdateServiceOrderStatusUseCase;
use App\Domain\ServiceOrder\Entities\ServiceOrder;
use App\Domain\ServiceOrder\Events\ServiceOrderStatusChanged;
use App\Domain\ServiceOrder\Interfaces\ServiceOrderRepositoryInterface;
use Illuminate\Support\Facades\Event;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class UpdateServiceOrderStatusUseCaseTest extends TestCase
{
    private MockInterface $repository;
    private UpdateServiceOrderStatusUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake();
        $this->repository = Mockery::mock(ServiceOrderRepositoryInterface::class);
        $this->useCase = new UpdateServiceOrderStatusUseCase($this->repository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeServiceOrder(): ServiceOrder
    {
        return ServiceOrder::create('cust-1', 'veh-1', [], []);
    }

    public function test_updates_status_and_dispatches_event(): void
    {
        $serviceOrder = $this->makeServiceOrder();

        $this->repository->shouldReceive('findById')->once()->with('os-1')->andReturn($serviceOrder);
        $this->repository->shouldReceive('update')->once()->with($serviceOrder);

        $result = $this->useCase->execute(new UpdateServiceOrderStatusDTO('os-1', ServiceOrder::STATUS_FINALIZADA));

        $this->assertSame(ServiceOrder::STATUS_FINALIZADA, $result->status);
        Event::assertDispatched(ServiceOrderStatusChanged::class);
    }

    public function test_throws_when_service_order_not_found(): void
    {
        $this->repository->shouldReceive('findById')->once()->with('os-404')->andReturnNull();
        $this->repository->shouldNotReceive('update');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Ordem de servico nao encontrada');

        $this->useCase->execute(new UpdateServiceOrderStatusDTO('os-404', ServiceOrder::STATUS_FINALIZADA));
    }
}