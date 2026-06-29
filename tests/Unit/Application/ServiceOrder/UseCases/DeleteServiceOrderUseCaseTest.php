<?php

namespace Tests\Unit\Application\ServiceOrder\UseCases;

use App\Application\ServiceOrder\DTOs\DeleteServiceOrderDTO;
use App\Application\ServiceOrder\UseCases\DeleteServiceOrderUseCase;
use App\Domain\ServiceOrder\Entities\ServiceOrder;
use App\Domain\ServiceOrder\Interfaces\ServiceOrderRepositoryInterface;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class DeleteServiceOrderUseCaseTest extends TestCase
{
    private MockInterface $repository;
    private DeleteServiceOrderUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(ServiceOrderRepositoryInterface::class);
        $this->useCase = new DeleteServiceOrderUseCase($this->repository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_deletes_existing_service_order(): void
    {
        $serviceOrder = ServiceOrder::create('cust-1', 'veh-1', [], []);

        $this->repository->shouldReceive('findById')->once()->with('os-1')->andReturn($serviceOrder);
        $this->repository->shouldReceive('delete')->once()->with('os-1');

        $this->useCase->execute(new DeleteServiceOrderDTO('os-1'));

        $this->assertTrue(true);
    }

    public function test_throws_when_service_order_not_found(): void
    {
        $this->repository->shouldReceive('findById')->once()->with('os-404')->andReturnNull();
        $this->repository->shouldNotReceive('delete');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Ordem de servico nao encontrada');

        $this->useCase->execute(new DeleteServiceOrderDTO('os-404'));
    }
}