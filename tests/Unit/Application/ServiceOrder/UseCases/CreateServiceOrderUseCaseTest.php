<?php

namespace Tests\Unit\Application\ServiceOrder\UseCases;

use App\Application\ServiceOrder\DTOs\CreateServiceOrderDTO;
use App\Application\ServiceOrder\UseCases\CreateServiceOrderUseCase;
use App\Domain\Customer\Entities\Customer;
use App\Domain\Customer\Interfaces\CustomerRepositoryInterface;
use App\Domain\Item\Entities\Item;
use App\Domain\Item\Interfaces\ItemRepositoryInterface;
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
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class CreateServiceOrderUseCaseTest extends TestCase
{
    private ServiceOrderRepositoryInterface&MockInterface $serviceOrderRepository;
    private CustomerRepositoryInterface&MockInterface $customerRepository;
    private VehicleRepositoryInterface&MockInterface $vehicleRepository;
    private ServiceRepositoryInterface&MockInterface $serviceRepository;
    private ItemRepositoryInterface&MockInterface $itemRepository;
    private CreateServiceOrderUseCase $useCase;
    private User $user;

    // CPF válido para testes
    private const VALID_CPF = '52998224725';

    protected function setUp(): void
    {
        parent::setUp();

        // Unit test: evita executar listeners reais que consultam banco fora do escopo do caso de uso.
        Event::fake();

        $this->user = new User([
            'name' => 'Test User',
            'email' => 'test@test.com',
            'document' => self::VALID_CPF,
        ]);
        $this->be($this->user);

        $this->serviceOrderRepository = Mockery::mock(ServiceOrderRepositoryInterface::class);
        $this->customerRepository     = Mockery::mock(CustomerRepositoryInterface::class);
        $this->vehicleRepository      = Mockery::mock(VehicleRepositoryInterface::class);
        $this->serviceRepository      = Mockery::mock(ServiceRepositoryInterface::class);
        $this->itemRepository         = Mockery::mock(ItemRepositoryInterface::class);

        $this->useCase = new CreateServiceOrderUseCase(
            $this->serviceOrderRepository,
            $this->customerRepository,
            $this->vehicleRepository,
            $this->serviceRepository,
            $this->itemRepository
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeDTO(array $overrides = []): CreateServiceOrderDTO
    {
        return new CreateServiceOrderDTO(
            vehicleBrand: $overrides['vehicleBrand'] ?? 'Toyota',
            vehicleModel: $overrides['vehicleModel'] ?? 'Corolla',
            vehicleYear:  $overrides['vehicleYear']  ?? 2020,
            vehiclePlate: $overrides['vehiclePlate'] ?? 'ABC1D23',
            services:     $overrides['services']     ?? [['service_id' => 'svc-1', 'quantity' => 1.0]],
            parts:        $overrides['parts']        ?? [],
            sendQuote:    $overrides['sendQuote']    ?? false,
        );
    }

    private function makeCustomer(): Customer
    {
        return new Customer('cust-uuid-1', 'Test User', 'test@test.com', 'Nao informado', self::VALID_CPF);
    }

    private function makeVehicle(): Vehicle
    {
        return new Vehicle('veh-uuid-1', 'Toyota', 'Corolla', 2020, new Plate('ABC1D23'));
    }

    private function makeService(string $id = 'svc-1', string $name = 'Oil Change', float $price = 100.0): Service
    {
        return new Service($id, $name, $price);
    }

    private function makeItem(string $id = 'item-1', float $unitPrice = 25.0): Item
    {
        return new Item(
            id: $id,
            name: 'Oil Filter',
            code: new ItemCode('FLT-001'),
            type: new ItemType(ItemType::PART),
            measureUnit: new MeasureUnit('un'),
            stockQuantity: 10.0,
            minimumQuantity: 2.0,
            description: null,
            unitPrice: $unitPrice
        );
    }

    /** Configura os mocks padrão para o fluxo sem peças. */
    private function setupHappyPathMocks(bool $newCustomer = true, bool $newVehicle = true): void
    {
        $customer = $this->makeCustomer();
        $vehicle  = $this->makeVehicle();

        if ($newCustomer) {
            $this->customerRepository->shouldReceive('findByDocument')->once()->andReturnNull();
            $this->customerRepository->shouldReceive('save')->once();
        } else {
            $this->customerRepository->shouldReceive('findByDocument')->once()->andReturn($customer);
            $this->customerRepository->shouldNotReceive('save');
        }

        if ($newVehicle) {
            $this->vehicleRepository->shouldReceive('findByPlate')->once()->andReturnNull();
            $this->vehicleRepository->shouldReceive('save')->once();
        } else {
            $this->vehicleRepository->shouldReceive('findByPlate')->once()->andReturn($vehicle);
            $this->vehicleRepository->shouldNotReceive('save');
        }

        $this->serviceRepository->shouldReceive('findById')->with('svc-1')->once()->andReturn($this->makeService());
        $this->serviceOrderRepository->shouldReceive('save')->once();
    }

    // -------------------------------------------------------------------------
    // Fluxo de criação
    // -------------------------------------------------------------------------

    public function test_creates_service_order_successfully(): void
    {
        $this->setupHappyPathMocks();

        $result = $this->useCase->execute($this->makeDTO());

        $this->assertInstanceOf(ServiceOrder::class, $result);
        $this->assertSame(ServiceOrder::STATUS_RECEBIDA, $result->status);
        $this->assertIsString($result->id);
        $this->assertNotEmpty($result->id);
    }

    public function test_creates_new_customer_when_not_found(): void
    {
        $this->customerRepository->shouldReceive('findByDocument')->once()->andReturnNull();
        $this->customerRepository->shouldReceive('save')->once()->with(Mockery::type(Customer::class));
        $this->vehicleRepository->shouldReceive('findByPlate')->once()->andReturnNull();
        $this->vehicleRepository->shouldReceive('save')->once();
        $this->serviceRepository->shouldReceive('findById')->once()->andReturn($this->makeService());
        $this->serviceOrderRepository->shouldReceive('save')->once();

        $result = $this->useCase->execute($this->makeDTO());

        // O customerId é gerado internamente; basta garantir que a OS foi criada
        $this->assertInstanceOf(ServiceOrder::class, $result);
        $this->assertNotEmpty($result->customerId);
    }

    public function test_reuses_existing_customer_without_creating_new_one(): void
    {
        $this->customerRepository->shouldReceive('findByDocument')->once()->andReturn($this->makeCustomer());
        $this->customerRepository->shouldNotReceive('save');
        $this->vehicleRepository->shouldReceive('findByPlate')->once()->andReturnNull();
        $this->vehicleRepository->shouldReceive('save')->once();
        $this->serviceRepository->shouldReceive('findById')->once()->andReturn($this->makeService());
        $this->serviceOrderRepository->shouldReceive('save')->once();

        $result = $this->useCase->execute($this->makeDTO());

        $this->assertSame('cust-uuid-1', $result->customerId);
    }

    public function test_creates_new_vehicle_when_not_found(): void
    {
        $this->customerRepository->shouldReceive('findByDocument')->once()->andReturn($this->makeCustomer());
        $this->vehicleRepository->shouldReceive('findByPlate')->once()->andReturnNull();
        $this->vehicleRepository->shouldReceive('save')->once()->with(Mockery::type(Vehicle::class));
        $this->serviceRepository->shouldReceive('findById')->once()->andReturn($this->makeService());
        $this->serviceOrderRepository->shouldReceive('save')->once();

        $result = $this->useCase->execute($this->makeDTO());

        // O vehicleId é gerado internamente; basta garantir que a OS foi criada
        $this->assertInstanceOf(ServiceOrder::class, $result);
        $this->assertNotEmpty($result->vehicleId);
    }

    public function test_reuses_existing_vehicle_without_creating_new_one(): void
    {
        $this->customerRepository->shouldReceive('findByDocument')->once()->andReturn($this->makeCustomer());
        $this->vehicleRepository->shouldReceive('findByPlate')->once()->andReturn($this->makeVehicle());
        $this->vehicleRepository->shouldNotReceive('save');
        $this->serviceRepository->shouldReceive('findById')->once()->andReturn($this->makeService());
        $this->serviceOrderRepository->shouldReceive('save')->once();

        $result = $this->useCase->execute($this->makeDTO());

        $this->assertSame('veh-uuid-1', $result->vehicleId);
    }

    // -------------------------------------------------------------------------
    // Snapshot de serviços e peças
    // -------------------------------------------------------------------------

    public function test_snapshots_service_name_and_price_from_catalog(): void
    {
        $this->setupHappyPathMocks();

        $dto    = $this->makeDTO(['services' => [['service_id' => 'svc-1', 'quantity' => 2.0]]]);
        $result = $this->useCase->execute($dto);

        $service = $result->services[0];
        $this->assertSame('svc-1', $service['service_id']);
        $this->assertSame('Oil Change', $service['name']);
        $this->assertSame(100.0, $service['unit_price']);
        $this->assertSame(2.0, $service['quantity']);
    }

    public function test_snapshots_item_name_and_price_from_catalog(): void
    {
        $this->customerRepository->shouldReceive('findByDocument')->once()->andReturn($this->makeCustomer());
        $this->vehicleRepository->shouldReceive('findByPlate')->once()->andReturnNull();
        $this->vehicleRepository->shouldReceive('save')->once();
        $this->serviceRepository->shouldReceive('findById')->with('svc-1')->once()->andReturn($this->makeService());
        $this->itemRepository->shouldReceive('findById')->with('item-1')->once()->andReturn($this->makeItem());
        $this->serviceOrderRepository->shouldReceive('save')->once();

        $dto = $this->makeDTO([
            'parts' => [['item_id' => 'item-1', 'quantity' => 3.0]],
        ]);

        $result = $this->useCase->execute($dto);

        $part = $result->parts[0];
        $this->assertSame('item-1', $part['item_id']);
        $this->assertSame('Oil Filter', $part['name']);
        $this->assertSame(25.0, $part['unit_price']);
        $this->assertSame(3.0, $part['quantity']);
    }

    // -------------------------------------------------------------------------
    // Cálculo de totais
    // -------------------------------------------------------------------------

    public function test_calculates_totals_correctly(): void
    {
        $this->customerRepository->shouldReceive('findByDocument')->once()->andReturn($this->makeCustomer());
        $this->vehicleRepository->shouldReceive('findByPlate')->once()->andReturnNull();
        $this->vehicleRepository->shouldReceive('save')->once();
        $this->serviceRepository->shouldReceive('findById')->with('svc-1')->once()->andReturn($this->makeService('svc-1', 'Oil Change', 200.0));
        $this->itemRepository->shouldReceive('findById')->with('item-1')->once()->andReturn($this->makeItem('item-1', 50.0));
        $this->serviceOrderRepository->shouldReceive('save')->once();

        $dto = $this->makeDTO([
            'services' => [['service_id' => 'svc-1', 'quantity' => 2.0]],  // 2 × 200 = 400
            'parts'    => [['item_id' => 'item-1', 'quantity' => 3.0]],    // 3 × 50  = 150
        ]);

        $result = $this->useCase->execute($dto);

        $this->assertSame(400.0, $result->servicesTotal);
        $this->assertSame(150.0, $result->partsTotal);
        $this->assertSame(550.0, $result->totalBudget);
    }

    public function test_creates_order_without_parts(): void
    {
        $this->setupHappyPathMocks();
        $this->itemRepository->shouldNotReceive('findById');

        $result = $this->useCase->execute($this->makeDTO(['parts' => []]));

        $this->assertSame([], $result->parts);
        $this->assertSame(0.0, $result->partsTotal);
    }

    // -------------------------------------------------------------------------
    // Comportamento do orçamento
    // -------------------------------------------------------------------------

    public function test_does_not_send_quote_when_flag_is_false(): void
    {
        $this->setupHappyPathMocks();

        $result = $this->useCase->execute($this->makeDTO(['sendQuote' => false]));

        $this->assertSame(ServiceOrder::STATUS_RECEBIDA, $result->status);
        $this->assertNull($result->quoteSentAt);
    }

    public function test_sends_quote_when_flag_is_true(): void
    {
        $this->setupHappyPathMocks();

        $result = $this->useCase->execute($this->makeDTO(['sendQuote' => true]));

        $this->assertSame(ServiceOrder::STATUS_AGUARDANDO_APROVACAO, $result->status);
        $this->assertNotNull($result->quoteSentAt);
    }

    // -------------------------------------------------------------------------
    // Erros de domínio
    // -------------------------------------------------------------------------

    public function test_throws_domain_exception_when_service_not_found(): void
    {
        $this->customerRepository->shouldReceive('findByDocument')->once()->andReturn($this->makeCustomer());
        $this->vehicleRepository->shouldReceive('findByPlate')->once()->andReturnNull();
        $this->vehicleRepository->shouldReceive('save')->once();
        $this->serviceRepository->shouldReceive('findById')->with('svc-inexistente')->once()->andReturnNull();
        $this->serviceOrderRepository->shouldNotReceive('save');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage("Servico 'svc-inexistente' nao encontrado.");

        $this->useCase->execute($this->makeDTO([
            'services' => [['service_id' => 'svc-inexistente', 'quantity' => 1.0]],
        ]));
    }

    public function test_throws_domain_exception_when_part_not_found(): void
    {
        $this->customerRepository->shouldReceive('findByDocument')->once()->andReturn($this->makeCustomer());
        $this->vehicleRepository->shouldReceive('findByPlate')->once()->andReturnNull();
        $this->vehicleRepository->shouldReceive('save')->once();
        $this->serviceRepository->shouldReceive('findById')->with('svc-1')->once()->andReturn($this->makeService());
        $this->itemRepository->shouldReceive('findById')->with('peca-inexistente')->once()->andReturnNull();
        $this->serviceOrderRepository->shouldNotReceive('save');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage("Peca 'peca-inexistente' nao encontrada.");

        $this->useCase->execute($this->makeDTO([
            'parts' => [['item_id' => 'peca-inexistente', 'quantity' => 1.0]],
        ]));
    }

    public function test_throws_exception_when_user_has_no_document(): void
    {
        $userSemDoc = new User([
            'name' => 'No Doc',
            'email' => 'nodoc@test.com',
            'document' => null,
        ]);
        $this->be($userSemDoc);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Usuario autenticado sem documento vinculado');

        $this->useCase->execute($this->makeDTO());
    }
}
