<?php

namespace Tests\Unit\Application\ServiceOrderService\UseCases;

use App\Application\ServiceOrderService\DTOs\StartServiceOrderServiceDTO;
use App\Application\ServiceOrderService\UseCases\StartServiceOrderServiceUseCase;
use App\Domain\ServiceOrder\Entities\ServiceOrder;
use App\Domain\ServiceOrder\Interfaces\ServiceOrderRepositoryInterface;
use App\Domain\ServiceOrderService\Entities\ServiceOrderService;
use App\Domain\ServiceOrderService\Interfaces\ServiceOrderServiceInterface;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class StartServiceOrderServiceUseCaseTest extends TestCase
{
    private ServiceOrderServiceInterface&MockInterface $serviceOrderServiceRepository;
    private ServiceOrderRepositoryInterface&MockInterface $serviceOrderRepository;
    private StartServiceOrderServiceUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->serviceOrderServiceRepository = Mockery::mock(ServiceOrderServiceInterface::class);
        $this->serviceOrderRepository = Mockery::mock(ServiceOrderRepositoryInterface::class);

        $this->useCase = new StartServiceOrderServiceUseCase(
            $this->serviceOrderServiceRepository,
            $this->serviceOrderRepository
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeServiceOrderService(string $serviceOrderId = 'os-1'): ServiceOrderService
    {
        return new ServiceOrderService(
            id: 'sos-1',
            service_order_id: $serviceOrderId,
            service_id: 'svc-1',
            quantity: 1,
            price: 100.0,
            started_at: null,
            finished_at: null
        );
    }

    private function makeServiceOrder(string $status): ServiceOrder
    {
        return new ServiceOrder(
            id: 'os-1',
            customerId: 'cust-1',
            vehicleId: 'veh-1',
            customerDocument: '52998224725',
            services: [],
            items: [],
            status: $status,
            servicesTotal: 0.0,
            itemsTotal: 0.0,
            totalBudget: 0.0,
            quoteSentAt: null,
            quoteApprovedAt: null
        );
    }

    public function test_start_service_when_order_is_in_execution(): void
    {
        $line = $this->makeServiceOrderService('os-1');
        $order = $this->makeServiceOrder(ServiceOrder::STATUS_EM_EXECUCAO);
        $started = new ServiceOrderService(
            id: 'sos-1',
            service_order_id: 'os-1',
            service_id: 'svc-1',
            quantity: 1,
            price: 100.0,
            started_at: now()->toDateTimeString(),
            finished_at: null,
            started_user_id: 99
        );

        $this->serviceOrderServiceRepository->shouldReceive('findById')->once()->with('sos-1')->andReturn($line);
        $this->serviceOrderRepository->shouldReceive('findById')->once()->with('os-1')->andReturn($order);
        $this->serviceOrderServiceRepository->shouldReceive('startService')->once()->with('sos-1', 99)->andReturn($started);

        $result = $this->useCase->execute(new StartServiceOrderServiceDTO('sos-1', 99));

        $this->assertNotNull($result->started_at);
        $this->assertSame(99, $result->started_user_id);
    }

    public function test_throws_when_service_order_service_not_found(): void
    {
        $this->serviceOrderServiceRepository->shouldReceive('findById')->once()->with('sos-404')->andReturnNull();
        $this->serviceOrderRepository->shouldNotReceive('findById');
        $this->serviceOrderServiceRepository->shouldNotReceive('startService');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Servico da OS nao encontrado.');

        $this->useCase->execute(new StartServiceOrderServiceDTO('sos-404', 10));
    }

    public function test_throws_when_parent_service_order_is_not_found(): void
    {
        $line = $this->makeServiceOrderService('os-404');

        $this->serviceOrderServiceRepository->shouldReceive('findById')->once()->with('sos-1')->andReturn($line);
        $this->serviceOrderRepository->shouldReceive('findById')->once()->with('os-404')->andReturnNull();
        $this->serviceOrderServiceRepository->shouldNotReceive('startService');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Ordem de servico nao encontrada.');

        $this->useCase->execute(new StartServiceOrderServiceDTO('sos-1', 10));
    }

    public function test_throws_when_order_status_is_not_in_execution(): void
    {
        $line = $this->makeServiceOrderService('os-1');
        $order = $this->makeServiceOrder(ServiceOrder::STATUS_AGUARDANDO_APROVACAO);

        $this->serviceOrderServiceRepository->shouldReceive('findById')->once()->with('sos-1')->andReturn($line);
        $this->serviceOrderRepository->shouldReceive('findById')->once()->with('os-1')->andReturn($order);
        $this->serviceOrderServiceRepository->shouldNotReceive('startService');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('A OS deve estar em_execucao para iniciar o servico.');

        $this->useCase->execute(new StartServiceOrderServiceDTO('sos-1', 10));
    }
}
