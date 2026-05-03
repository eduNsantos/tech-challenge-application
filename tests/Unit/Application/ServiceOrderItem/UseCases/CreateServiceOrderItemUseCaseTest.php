<?php

namespace Tests\Unit\Application\ServiceOrderItem\UseCases;

use App\Application\ServiceOrderItem\DTOs\CreateServiceOrderItemDTO;
use App\Application\ServiceOrderItem\UseCases\CreateServiceOrderItemUseCase;
use App\Domain\ServiceOrderItem\Entities\ServiceOrderItem;
use App\Domain\ServiceOrderItem\Interfaces\ServiceOrderItemInterface;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class CreateServiceOrderItemUseCaseTest extends TestCase
{
    private ServiceOrderItemInterface&MockInterface $repository;
    private CreateServiceOrderItemUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = Mockery::mock(ServiceOrderItemInterface::class);
        $this->useCase = new CreateServiceOrderItemUseCase($this->repository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_execute_creates_service_order_item(): void
    {
        $dto = new CreateServiceOrderItemDTO('os-1', 'item-1', 2, 99.9);

        $entity = new ServiceOrderItem(
            id: 'soi-1',
            service_order_id: 'os-1',
            item_id: 'item-1',
            quantity: 2,
            price: 99.9
        );

        $this->repository
            ->shouldReceive('createServiceOrderItem')
            ->once()
            ->with($dto)
            ->andReturn($entity);

        $result = $this->useCase->execute($dto);

        $this->assertSame('soi-1', $result->id);
        $this->assertSame(2, $result->quantity);
    }
}
