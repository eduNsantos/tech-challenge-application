<?php

namespace Tests\Unit\Application\ServiceOrder\UseCases;

use App\Application\ServiceOrder\DTOs\UpdateServiceOrderDTO;
use App\Application\ServiceOrder\UseCases\UpdateServiceOrderUseCase;
use App\Domain\Item\Entities\Item;
use App\Domain\Item\Entities\StockMovement;
use App\Domain\Item\Interfaces\ItemRepositoryInterface;
use App\Domain\Item\Interfaces\StockMovementRepositoryInterface;
use App\Domain\Item\ValueObjects\ItemCode;
use App\Domain\Item\ValueObjects\ItemType;
use App\Domain\Item\ValueObjects\MeasureUnit;
use App\Domain\Service\Entities\Service;
use App\Domain\Service\Interfaces\ServiceRepositoryInterface;
use App\Domain\ServiceOrder\Entities\ServiceOrder;
use App\Domain\ServiceOrder\Interfaces\ServiceOrderRepositoryInterface;
use App\Domain\Vehicle\Entities\Vehicle;
use App\Domain\Vehicle\Interfaces\VehicleRepositoryInterface;
use App\Domain\Vehicle\ValueObjects\Plate;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class UpdateServiceOrderUseCaseTest extends TestCase
{
    private ServiceOrderRepositoryInterface&MockInterface $serviceOrderRepository;
    private ServiceRepositoryInterface&MockInterface $serviceRepository;
    private ItemRepositoryInterface&MockInterface $itemRepository;
    private StockMovementRepositoryInterface&MockInterface $stockMovementRepository;
    private VehicleRepositoryInterface&MockInterface $vehicleRepository;
    private UpdateServiceOrderUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->serviceOrderRepository = Mockery::mock(ServiceOrderRepositoryInterface::class);
        $this->serviceRepository = Mockery::mock(ServiceRepositoryInterface::class);
        $this->itemRepository = Mockery::mock(ItemRepositoryInterface::class);
        $this->stockMovementRepository = Mockery::mock(StockMovementRepositoryInterface::class);
        $this->vehicleRepository = Mockery::mock(VehicleRepositoryInterface::class);

        $this->useCase = new UpdateServiceOrderUseCase(
            $this->serviceOrderRepository,
            $this->serviceRepository,
            $this->itemRepository,
            $this->stockMovementRepository,
            $this->vehicleRepository
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeServiceOrder(): ServiceOrder
    {
        return ServiceOrder::create(
            'cust-1',
            'veh-1',
            '52998224725',
            [['service_id' => 'svc-1', 'name' => 'Old', 'quantity' => 1.0, 'unit_price' => 50.0]],
            [['item_id' => 'item-1', 'name' => 'Filtro', 'quantity' => 1.0, 'unit_price' => 20.0]]
        );
    }

    private function makeItem(string $id = 'item-1', float $stock = 10.0): Item
    {
        return new Item(
            id: $id,
            name: 'Filtro',
            code: new ItemCode('FLT-001'),
            type: new ItemType(ItemType::PART),
            measureUnit: new MeasureUnit('un'),
            stockQuantity: $stock,
            minimumQuantity: 2.0,
            description: null,
            unitPrice: 20.0
        );
    }

    private function makeVehicle(string $id = 'veh-2'): Vehicle
    {
        return new Vehicle(
            id: $id,
            brand: 'Toyota',
            model: 'Corolla',
            year: 2020,
            plate: new Plate('ABC1D23')
        );
    }

    public function test_updates_services_and_items_data(): void
    {
        $serviceOrder = $this->makeServiceOrder();

        $this->serviceOrderRepository->shouldReceive('findById')->once()->with('os-1')->andReturn($serviceOrder);
        $this->serviceRepository->shouldReceive('findById')->once()->with('svc-2')->andReturn(new Service('svc-2', 'Alinhamento', 90.0));
        $this->itemRepository->shouldReceive('findById')->once()->with('item-2')->andReturn($this->makeItem('item-2', 12.0));
        $this->serviceOrderRepository->shouldReceive('update')->once()->with($serviceOrder);

        $result = $this->useCase->execute(new UpdateServiceOrderDTO(
            id: 'os-1',
            services: [['service_id' => 'svc-2', 'quantity' => 2.0]],
            items: [['item_id' => 'item-2', 'quantity' => 3.0]],
            vehicleId: null,
            status: null,
            sendQuote: null,
            approveQuote: null
        ));

        $this->assertSame('Alinhamento', $result->services[0]['name']);
        $this->assertSame('item-2', $result->items[0]['item_id']);
    }

    public function test_approving_quote_withdraws_stock_and_records_movement(): void
    {
        $serviceOrder = $this->makeServiceOrder();
        $item = $this->makeItem('item-1', 10.0);

        $this->serviceOrderRepository->shouldReceive('findById')->once()->with('os-1')->andReturn($serviceOrder);
        $this->itemRepository->shouldReceive('findById')->once()->with('item-1')->andReturn($item);
        $this->itemRepository->shouldReceive('update')->once()->with($item);
        $this->stockMovementRepository->shouldReceive('save')->once()->with(Mockery::type(StockMovement::class));
        $this->serviceOrderRepository->shouldReceive('update')->once()->with($serviceOrder);

        $result = $this->useCase->execute(new UpdateServiceOrderDTO(
            id: 'os-1',
            services: null,
            items: null,
            vehicleId: null,
            status: null,
            sendQuote: null,
            approveQuote: true
        ));

        $this->assertSame(ServiceOrder::STATUS_EM_EXECUCAO, $result->status);
        $this->assertNotNull($result->quoteApprovedAt);
        $this->assertSame(9.0, $item->stockQuantity);
    }

    public function test_throws_when_service_order_not_found(): void
    {
        $this->serviceOrderRepository->shouldReceive('findById')->once()->with('os-404')->andReturnNull();
        $this->serviceOrderRepository->shouldNotReceive('update');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Ordem de servico nao encontrada');

        $this->useCase->execute(new UpdateServiceOrderDTO('os-404', null, null, null, null, null, null));
    }

    public function test_throws_when_service_not_found_during_update(): void
    {
        $serviceOrder = $this->makeServiceOrder();

        $this->serviceOrderRepository->shouldReceive('findById')->once()->with('os-1')->andReturn($serviceOrder);
        $this->serviceRepository->shouldReceive('findById')->once()->with('svc-x')->andReturnNull();
        $this->serviceOrderRepository->shouldNotReceive('update');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage("Servico 'svc-x' nao encontrado.");

        $this->useCase->execute(new UpdateServiceOrderDTO(
            id: 'os-1',
            services: [['service_id' => 'svc-x', 'quantity' => 1]],
            items: null,
            vehicleId: null,
            status: null,
            sendQuote: null,
            approveQuote: null
        ));
    }

    public function test_throws_when_item_not_found_during_update(): void
    {
        $serviceOrder = $this->makeServiceOrder();

        $this->serviceOrderRepository->shouldReceive('findById')->once()->with('os-1')->andReturn($serviceOrder);
        $this->itemRepository->shouldReceive('findById')->once()->with('item-x')->andReturnNull();
        $this->serviceOrderRepository->shouldNotReceive('update');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage("Peca 'item-x' nao encontrada.");

        $this->useCase->execute(new UpdateServiceOrderDTO(
            id: 'os-1',
            services: null,
            items: [['item_id' => 'item-x', 'quantity' => 1]],
            vehicleId: null,
            status: null,
            sendQuote: null,
            approveQuote: null
        ));
    }

    public function test_throws_when_item_id_is_empty_in_resolveItems(): void
    {
        $serviceOrder = $this->makeServiceOrder();

        $this->serviceOrderRepository->shouldReceive('findById')->once()->andReturn($serviceOrder);
        $this->serviceOrderRepository->shouldNotReceive('update');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Item da OS sem identificador informado.');

        $this->useCase->execute(new UpdateServiceOrderDTO(
            id: 'os-1',
            services: null,
            items: [['quantity' => 1]],
            vehicleId: null,
            status: null,
            sendQuote: null,
            approveQuote: null
        ));
    }

    public function test_throws_when_item_not_found_during_withdrawal(): void
    {
        $serviceOrder = $this->makeServiceOrder();

        $this->serviceOrderRepository->shouldReceive('findById')->once()->andReturn($serviceOrder);
        $this->itemRepository->shouldReceive('findById')->with('item-1')->andReturnNull();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage("Peca 'item-1' nao encontrada ao baixar estoque.");

        $this->useCase->execute(new UpdateServiceOrderDTO(
            id: 'os-1',
            services: null,
            items: null,
            vehicleId: null,
            status: null,
            sendQuote: null,
            approveQuote: true
        ));
    }

    public function test_changes_status_when_status_is_provided(): void
    {
        $serviceOrder = $this->makeServiceOrder();

        $this->serviceOrderRepository->shouldReceive('findById')->once()->andReturn($serviceOrder);
        $this->serviceOrderRepository->shouldReceive('update')->once()->with($serviceOrder);

        $result = $this->useCase->execute(new UpdateServiceOrderDTO(
            id: 'os-1',
            services: null,
            items: null,
            vehicleId: null,
            status: ServiceOrder::STATUS_EM_EXECUCAO,
            sendQuote: null,
            approveQuote: null
        ));

        $this->assertSame(ServiceOrder::STATUS_EM_EXECUCAO, $result->status);
    }

    public function test_updates_vehicle_when_vehicle_id_is_provided(): void
    {
        $serviceOrder = $this->makeServiceOrder();

        $this->serviceOrderRepository->shouldReceive('findById')->once()->with('os-1')->andReturn($serviceOrder);
        $this->vehicleRepository->shouldReceive('findById')->once()->with('veh-2')->andReturn($this->makeVehicle('veh-2'));
        $this->serviceOrderRepository->shouldReceive('update')->once()->with($serviceOrder);

        $result = $this->useCase->execute(new UpdateServiceOrderDTO(
            id: 'os-1',
            services: null,
            items: null,
            vehicleId: 'veh-2',
            status: null,
            sendQuote: null,
            approveQuote: null
        ));

        $this->assertSame('veh-2', $result->vehicleId);
    }

    public function test_throws_when_vehicle_not_found_during_update(): void
    {
        $serviceOrder = $this->makeServiceOrder();

        $this->serviceOrderRepository->shouldReceive('findById')->once()->with('os-1')->andReturn($serviceOrder);
        $this->vehicleRepository->shouldReceive('findById')->once()->with('veh-x')->andReturnNull();
        $this->serviceOrderRepository->shouldNotReceive('update');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage("Veiculo 'veh-x' nao encontrado.");

        $this->useCase->execute(new UpdateServiceOrderDTO(
            id: 'os-1',
            services: null,
            items: null,
            vehicleId: 'veh-x',
            status: null,
            sendQuote: null,
            approveQuote: null
        ));
    }
}
