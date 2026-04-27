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
    private MockInterface $serviceOrderRepository;
    private MockInterface $vehicleRepository;
    private MockInterface $serviceRepository;
    private MockInterface $itemRepository;
    private MockInterface $stockMovementRepository;
    private UpdateServiceOrderUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->serviceOrderRepository = Mockery::mock(ServiceOrderRepositoryInterface::class);
        $this->vehicleRepository = Mockery::mock(VehicleRepositoryInterface::class);
        $this->serviceRepository = Mockery::mock(ServiceRepositoryInterface::class);
        $this->itemRepository = Mockery::mock(ItemRepositoryInterface::class);
        $this->stockMovementRepository = Mockery::mock(StockMovementRepositoryInterface::class);

        $this->useCase = new UpdateServiceOrderUseCase(
            $this->serviceOrderRepository,
            $this->vehicleRepository,
            $this->serviceRepository,
            $this->itemRepository,
            $this->stockMovementRepository
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

    private function makeVehicle(string $id = 'veh-1', string $plate = 'ABC1D23'): Vehicle
    {
        return new Vehicle($id, 'Toyota', 'Corolla', 2020, new Plate($plate));
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

    public function test_updates_services_and_vehicle_data(): void
    {
        $serviceOrder = $this->makeServiceOrder();
        $vehicle = $this->makeVehicle();

        $this->serviceOrderRepository->shouldReceive('findById')->once()->with('os-1')->andReturn($serviceOrder);
        $this->serviceRepository->shouldReceive('findById')->once()->with('svc-2')->andReturn(new Service('svc-2', 'Alinhamento', 90.0));
        $this->itemRepository->shouldNotReceive('findById');
        $this->vehicleRepository->shouldReceive('findById')->once()->with('veh-1')->andReturn($vehicle);
        $this->vehicleRepository->shouldReceive('findByPlate')->once()->with('DEF2G34')->andReturnNull();
        $this->vehicleRepository->shouldReceive('update')->once()->with($vehicle);
        $this->serviceOrderRepository->shouldReceive('update')->once()->with($serviceOrder);

        $result = $this->useCase->execute(new UpdateServiceOrderDTO(
            id: 'os-1',
            services: [['service_id' => 'svc-2', 'quantity' => 2.0]],
            parts: null,
            vehicleBrand: 'Honda',
            vehicleModel: 'Civic',
            vehicleYear: 2024,
            vehiclePlate: 'DEF2G34',
            status: null,
            sendQuote: null,
            approveQuote: null
        ));

        $this->assertSame('Alinhamento', $result->services[0]['name']);
        $this->assertSame('Honda', $vehicle->brand);
        $this->assertSame('DEF2G34', $vehicle->plate->getValue());
    }

    public function test_approving_quote_withdraws_stock_and_records_movement(): void
    {
        $serviceOrder = $this->makeServiceOrder();
        $item = $this->makeItem('item-1', 10.0);

        $this->serviceOrderRepository->shouldReceive('findById')->once()->with('os-1')->andReturn($serviceOrder);
        $this->vehicleRepository->shouldNotReceive('findById');
        $this->itemRepository->shouldReceive('findById')->once()->with('item-1')->andReturn($item);
        $this->itemRepository->shouldReceive('update')->once()->with($item);
        $this->stockMovementRepository->shouldReceive('save')->once()->with(Mockery::type(StockMovement::class));
        $this->serviceOrderRepository->shouldReceive('update')->once()->with($serviceOrder);

        $result = $this->useCase->execute(new UpdateServiceOrderDTO(
            id: 'os-1',
            services: null,
            parts: null,
            vehicleBrand: null,
            vehicleModel: null,
            vehicleYear: null,
            vehiclePlate: null,
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

        $this->useCase->execute(new UpdateServiceOrderDTO('os-404', null, null, null, null, null, null, null, null, null));
    }

    public function test_throws_when_vehicle_of_service_order_is_not_found(): void
    {
        $serviceOrder = $this->makeServiceOrder();

        $this->serviceOrderRepository->shouldReceive('findById')->once()->with('os-1')->andReturn($serviceOrder);
        $this->vehicleRepository->shouldReceive('findById')->once()->with('veh-1')->andReturnNull();
        $this->serviceOrderRepository->shouldNotReceive('update');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Veiculo da ordem de servico nao encontrado');

        $this->useCase->execute(new UpdateServiceOrderDTO('os-1', null, null, 'Honda', null, null, null, null, null, null));
    }

    public function test_throws_when_plate_is_already_used_by_another_vehicle(): void
    {
        $serviceOrder = $this->makeServiceOrder();
        $vehicle = $this->makeVehicle('veh-1', 'ABC1D23');
        $otherVehicle = $this->makeVehicle('veh-2', 'DEF2G34');

        $this->serviceOrderRepository->shouldReceive('findById')->once()->with('os-1')->andReturn($serviceOrder);
        $this->vehicleRepository->shouldReceive('findById')->once()->with('veh-1')->andReturn($vehicle);
        $this->vehicleRepository->shouldReceive('findByPlate')->once()->with('DEF2G34')->andReturn($otherVehicle);
        $this->vehicleRepository->shouldNotReceive('update');
        $this->serviceOrderRepository->shouldNotReceive('update');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Placa ja cadastrada para outro veiculo');

        $this->useCase->execute(new UpdateServiceOrderDTO('os-1', null, null, null, null, null, 'DEF2G34', null, null, null));
    }
}