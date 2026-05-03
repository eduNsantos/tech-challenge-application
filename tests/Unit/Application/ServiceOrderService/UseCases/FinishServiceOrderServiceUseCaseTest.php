<?php

namespace Tests\Unit\Application\ServiceOrderService\UseCases;

use App\Application\ServiceOrderService\DTOs\FinishServiceOrderServiceDTO;
use App\Application\ServiceOrderService\UseCases\FinishServiceOrderServiceUseCase;
use App\Domain\ServiceOrderService\Entities\ServiceOrderService;
use App\Domain\ServiceOrderService\Interfaces\ServiceOrderServiceInterface;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class FinishServiceOrderServiceUseCaseTest extends TestCase
{
    private ServiceOrderServiceInterface&MockInterface $serviceOrderServiceRepository;
    private FinishServiceOrderServiceUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->serviceOrderServiceRepository = Mockery::mock(ServiceOrderServiceInterface::class);
        $this->useCase = new FinishServiceOrderServiceUseCase($this->serviceOrderServiceRepository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_finish_service_and_set_finished_user(): void
    {
        $finished = new ServiceOrderService(
            id: 'sos-1',
            service_order_id: 'os-1',
            service_id: 'svc-1',
            quantity: 1,
            price: 100.0,
            started_at: now()->subHour()->toDateTimeString(),
            finished_at: now()->toDateTimeString(),
            started_user_id: 7,
            finished_user_id: 9
        );

        $this->serviceOrderServiceRepository
            ->shouldReceive('finishService')
            ->once()
            ->with('sos-1', 9)
            ->andReturn($finished);

        $result = $this->useCase->execute(new FinishServiceOrderServiceDTO('sos-1', 9));

        $this->assertNotNull($result->finished_at);
        $this->assertSame(9, $result->finished_user_id);
    }

    public function test_finish_service_without_user_id(): void
    {
        $finished = new ServiceOrderService(
            id: 'sos-1',
            service_order_id: 'os-1',
            service_id: 'svc-1',
            quantity: 1,
            price: 100.0,
            started_at: null,
            finished_at: now()->toDateTimeString(),
            started_user_id: null,
            finished_user_id: null
        );

        $this->serviceOrderServiceRepository
            ->shouldReceive('finishService')
            ->once()
            ->with('sos-1', null)
            ->andReturn($finished);

        $result = $this->useCase->execute(new FinishServiceOrderServiceDTO('sos-1'));

        $this->assertNotNull($result->finished_at);
        $this->assertNull($result->finished_user_id);
    }
}
