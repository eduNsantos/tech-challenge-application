<?php

namespace Tests\Unit\Application\ServiceOrder\UseCases;

use App\Application\ServiceOrder\UseCases\RejectServiceOrderByTokenUseCase;
use App\Domain\ServiceOrder\Entities\ServiceOrder;
use App\Domain\ServiceOrder\Interfaces\ServiceOrderRepositoryInterface;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class RejectServiceOrderByTokenUseCaseTest extends TestCase
{
    private ServiceOrderRepositoryInterface&MockInterface $serviceOrderRepository;
    private RejectServiceOrderByTokenUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->serviceOrderRepository = Mockery::mock(ServiceOrderRepositoryInterface::class);

        $this->useCase = new RejectServiceOrderByTokenUseCase(
            $this->serviceOrderRepository
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeServiceOrderWithToken(string $token): ServiceOrder
    {
        $so = ServiceOrder::create(
            'cust-1',
            'veh-1',
            [['service_id' => 'svc-1', 'name' => 'Alinhamento', 'quantity' => 1.0, 'unit_price' => 100.0]],
            []
        );
        $so->sendQuoteForApproval();
        $so->approvalToken = $token;

        return $so;
    }

    public function test_throws_when_token_not_found(): void
    {
        $this->serviceOrderRepository
            ->shouldReceive('findByApprovalToken')
            ->with('invalid-token')
            ->andReturn(null);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Token de aprovacao invalido ou expirado.');

        $this->useCase->execute('invalid-token');
    }

    public function test_throws_when_service_order_is_not_awaiting_approval(): void
    {
        $serviceOrder = ServiceOrder::create(
            'cust-1',
            'veh-1',
            [['service_id' => 'svc-1', 'name' => 'Servico', 'quantity' => 1.0, 'unit_price' => 50.0]],
            []
        );

        $this->serviceOrderRepository
            ->shouldReceive('findByApprovalToken')
            ->with('some-token')
            ->andReturn($serviceOrder);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Esta ordem de servico nao esta aguardando aprovacao.');

        $this->useCase->execute('some-token');
    }

    public function test_rejects_successfully_and_clears_token(): void
    {
        $token = 'a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2';
        $serviceOrder = $this->makeServiceOrderWithToken($token);

        $this->serviceOrderRepository
            ->shouldReceive('findByApprovalToken')
            ->with($token)
            ->andReturn($serviceOrder);

        $this->serviceOrderRepository->shouldReceive('update')->once();

        $result = $this->useCase->execute($token);

        $this->assertSame(ServiceOrder::STATUS_EM_DIAGNOSTICO, $result->status);
        $this->assertNull($result->approvalToken);
    }
}
