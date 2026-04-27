<?php

namespace Tests\Unit\Application\ServiceOrder\UseCases;

use App\Application\ServiceOrder\DTOs\ShowServiceOrderDTO;
use App\Application\ServiceOrder\UseCases\ShowServiceOrderUseCase;
use App\Domain\ServiceOrder\Entities\ServiceOrder;
use App\Domain\ServiceOrder\Interfaces\ServiceOrderRepositoryInterface;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class ShowServiceOrderUseCaseTest extends TestCase
{
    private MockInterface $repository;
    private ShowServiceOrderUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(ServiceOrderRepositoryInterface::class);
        $this->useCase = new ShowServiceOrderUseCase($this->repository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeServiceOrder(): ServiceOrder
    {
        return ServiceOrder::create('cust-1', 'veh-1', '52998224725', [], []);
    }

    public function test_returns_service_order_when_found(): void
    {
        $serviceOrder = $this->makeServiceOrder();

        $this->repository->shouldReceive('findById')->once()->with('os-1')->andReturn($serviceOrder);

        $result = $this->useCase->execute(new ShowServiceOrderDTO('os-1'));

        $this->assertSame($serviceOrder, $result);
    }

    public function test_throws_when_service_order_not_found(): void
    {
        $this->repository->shouldReceive('findById')->once()->with('os-404')->andReturnNull();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Ordem de servico nao encontrada');

        $this->useCase->execute(new ShowServiceOrderDTO('os-404'));
    }
}