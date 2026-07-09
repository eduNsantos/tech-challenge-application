<?php

namespace Tests\Unit\Application\ServiceOrder\UseCases;

use App\Application\ServiceOrder\UseCases\ApproveServiceOrderByTokenUseCase;
use App\Domain\Item\Entities\Item;
use App\Domain\Item\Interfaces\ItemRepositoryInterface;
use App\Domain\Item\Interfaces\StockMovementRepositoryInterface;
use App\Domain\Item\ValueObjects\ItemCode;
use App\Domain\Item\ValueObjects\ItemType;
use App\Domain\Item\ValueObjects\MeasureUnit;
use App\Domain\ServiceOrder\Entities\ServiceOrder;
use App\Domain\ServiceOrder\Interfaces\ServiceOrderRepositoryInterface;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class ApproveServiceOrderByTokenUseCaseTest extends TestCase
{
    private ServiceOrderRepositoryInterface&MockInterface $serviceOrderRepository;
    private ItemRepositoryInterface&MockInterface $itemRepository;
    private StockMovementRepositoryInterface&MockInterface $stockMovementRepository;
    private ApproveServiceOrderByTokenUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->serviceOrderRepository = Mockery::mock(ServiceOrderRepositoryInterface::class);
        $this->itemRepository = Mockery::mock(ItemRepositoryInterface::class);
        $this->stockMovementRepository = Mockery::mock(StockMovementRepositoryInterface::class);

        $this->useCase = new ApproveServiceOrderByTokenUseCase(
            $this->serviceOrderRepository,
            $this->itemRepository,
            $this->stockMovementRepository
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
            [['item_id' => 'item-1', 'name' => 'Filtro', 'quantity' => 2.0, 'unit_price' => 20.0]]
        );
        $so->sendQuoteForApproval();
        // Override token with controlled value for assertions
        $so->approvalToken = $token;
        return $so;
    }

    private function makeItem(float $stock = 10.0): Item
    {
        return new Item(
            id: 'item-1',
            name: 'Filtro',
            code: new ItemCode('FLT-001'),
            type: new ItemType(ItemType::PART),
            measureUnit: new MeasureUnit('un'),
            stockQuantity: $stock,
            minimumQuantity: 1.0,
            description: null,
            unitPrice: 20.0
        );
    }

    public function test_throws_when_token_not_found(): void
    {
        $this->serviceOrderRepository
            ->shouldReceive('findByApprovalToken')
            ->with('invalid-token')
            ->andReturn(null);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Token de aprovação inválido ou expirado.');

        $this->useCase->execute('invalid-token');
    }

    public function test_throws_when_service_order_is_not_awaiting_approval(): void
    {
        $serviceOrder = ServiceOrder::create(
            'cust-1', 'veh-1',
            [['service_id' => 'svc-1', 'name' => 'Serviço', 'quantity' => 1.0, 'unit_price' => 50.0]],
            []
        );
        // status = recebida (not aguardando_aprovacao)

        $this->serviceOrderRepository
            ->shouldReceive('findByApprovalToken')
            ->with('some-token')
            ->andReturn($serviceOrder);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Esta ordem de serviço não está aguardando aprovação.');

        $this->useCase->execute('some-token');
    }

    public function test_approves_successfully_and_clears_token(): void
    {
        $token = 'a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2';
        $serviceOrder = $this->makeServiceOrderWithToken($token);
        $item = $this->makeItem();

        $this->serviceOrderRepository
            ->shouldReceive('findByApprovalToken')
            ->with($token)
            ->andReturn($serviceOrder);

        $this->itemRepository
            ->shouldReceive('findById')
            ->with('item-1')
            ->andReturn($item);

        $this->itemRepository->shouldReceive('update')->once();
        $this->stockMovementRepository->shouldReceive('save')->once();
        $this->serviceOrderRepository->shouldReceive('update')->once();

        $result = $this->useCase->execute($token);

        $this->assertSame(ServiceOrder::STATUS_EM_EXECUCAO, $result->status);
        $this->assertNull($result->approvalToken);
        $this->assertNotNull($result->quoteApprovedAt);
    }

    public function test_approves_without_items_does_not_touch_stock(): void
    {
        $so = ServiceOrder::create(
            'cust-1', 'veh-1',
            [['service_id' => 'svc-1', 'name' => 'Alinhamento', 'quantity' => 1.0, 'unit_price' => 100.0]],
            []
        );
        $so->sendQuoteForApproval();
        $token = $so->approvalToken;

        $this->serviceOrderRepository
            ->shouldReceive('findByApprovalToken')
            ->with($token)
            ->andReturn($so);

        $this->itemRepository->shouldNotReceive('findById');
        $this->itemRepository->shouldNotReceive('update');
        $this->stockMovementRepository->shouldNotReceive('save');
        $this->serviceOrderRepository->shouldReceive('update')->once();

        $result = $this->useCase->execute($token);

        $this->assertSame(ServiceOrder::STATUS_EM_EXECUCAO, $result->status);
        $this->assertNull($result->approvalToken);
    }

    public function test_skips_stock_movement_when_item_not_found(): void
    {
        $token = 'deadbeefdeadbeefdeadbeefdeadbeefdeadbeefdeadbeefdeadbeefdeadbeef';
        $serviceOrder = $this->makeServiceOrderWithToken($token);

        $this->serviceOrderRepository
            ->shouldReceive('findByApprovalToken')
            ->with($token)
            ->andReturn($serviceOrder);

        $this->itemRepository
            ->shouldReceive('findById')
            ->with('item-1')
            ->andReturn(null);

        $this->itemRepository->shouldNotReceive('update');
        $this->stockMovementRepository->shouldNotReceive('save');
        $this->serviceOrderRepository->shouldReceive('update')->once();

        $result = $this->useCase->execute($token);

        $this->assertSame(ServiceOrder::STATUS_EM_EXECUCAO, $result->status);
    }
}
