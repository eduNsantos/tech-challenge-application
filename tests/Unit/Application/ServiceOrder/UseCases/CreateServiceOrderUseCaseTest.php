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
use App\Domain\ServiceOrderItem\Interfaces\ServiceOrderItemInterface;
use App\Domain\ServiceOrderService\Interfaces\ServiceOrderServiceInterface;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class CreateServiceOrderUseCaseTest extends TestCase
{
    private ServiceOrderRepositoryInterface&MockInterface $serviceOrderRepository;
    private CustomerRepositoryInterface&MockInterface $customerRepository;
    private ServiceRepositoryInterface&MockInterface $serviceRepository;
    private ItemRepositoryInterface&MockInterface $itemRepository;
    private ServiceOrderItemInterface&MockInterface $serviceOrderItemRepository;
    private ServiceOrderServiceInterface&MockInterface $serviceOrderServiceRepository;
    private CreateServiceOrderUseCase $useCase;
    private User $user;

    private const VALID_CPF = '52998224725';

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake();
        DB::shouldReceive('transaction')->andReturnUsing(static fn ($callback) => $callback());

        $this->user = new User([
            'name' => 'Test User',
            'email' => 'test@test.com',
            'document' => self::VALID_CPF,
        ]);

        $this->serviceOrderRepository = Mockery::mock(ServiceOrderRepositoryInterface::class);
        $this->customerRepository = Mockery::mock(CustomerRepositoryInterface::class);
        $this->serviceRepository = Mockery::mock(ServiceRepositoryInterface::class);
        $this->itemRepository = Mockery::mock(ItemRepositoryInterface::class);
        $this->serviceOrderItemRepository = Mockery::mock(ServiceOrderItemInterface::class);
        $this->serviceOrderServiceRepository = Mockery::mock(ServiceOrderServiceInterface::class);

        $this->useCase = new CreateServiceOrderUseCase(
            $this->serviceOrderRepository,
            $this->customerRepository,
            $this->serviceRepository,
            $this->itemRepository,
            $this->serviceOrderItemRepository,
            $this->serviceOrderServiceRepository
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeDTO(array $overrides = []): CreateServiceOrderDTO
    {
        return new CreateServiceOrderDTO(
            user: $overrides['user'] ?? $this->user,
            vehicleId: $overrides['vehicleId'] ?? 'veh-1',
            services: $overrides['services'] ?? [['service_id' => 'svc-1', 'quantity' => 1.0]],
            items: $overrides['items'] ?? [['item_id' => 'item-1', 'quantity' => 2.0]],
            sendQuote: $overrides['sendQuote'] ?? false,
        );
    }

    private function makeCustomer(): Customer
    {
        return new Customer('cust-1', 'Test User', 'test@test.com', 'Nao informado', self::VALID_CPF);
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

    public function test_creates_service_order_successfully(): void
    {
        $this->customerRepository->shouldReceive('findByDocument')->once()->andReturn($this->makeCustomer());
        $this->serviceRepository->shouldReceive('findById')->with('svc-1')->once()->andReturn($this->makeService());
        $this->itemRepository->shouldReceive('findById')->with('item-1')->once()->andReturn($this->makeItem());

        $this->serviceOrderRepository->shouldReceive('save')->once();
        $this->serviceOrderServiceRepository->shouldReceive('createServiceOrderService')->once();
        $this->serviceOrderItemRepository->shouldReceive('createServiceOrderItem')->once();

        $result = $this->useCase->execute($this->makeDTO());

        $this->assertInstanceOf(ServiceOrder::class, $result);
        $this->assertSame(ServiceOrder::STATUS_RECEBIDA, $result->status);
        $this->assertNotEmpty($result->id);
    }

    public function test_creates_new_customer_when_not_found(): void
    {
        $this->customerRepository->shouldReceive('findByDocument')->once()->andReturnNull();
        $this->customerRepository->shouldReceive('save')->once();
        $this->serviceRepository->shouldReceive('findById')->once()->andReturn($this->makeService());
        $this->itemRepository->shouldReceive('findById')->once()->andReturn($this->makeItem());

        $this->serviceOrderRepository->shouldReceive('save')->once();
        $this->serviceOrderServiceRepository->shouldReceive('createServiceOrderService')->once();
        $this->serviceOrderItemRepository->shouldReceive('createServiceOrderItem')->once();

        $result = $this->useCase->execute($this->makeDTO());

        $this->assertNotEmpty($result->customerId);
    }

    public function test_snapshots_service_name_and_price_from_catalog(): void
    {
        $this->customerRepository->shouldReceive('findByDocument')->once()->andReturn($this->makeCustomer());
        $this->serviceRepository->shouldReceive('findById')->with('svc-1')->once()->andReturn($this->makeService('svc-1', 'Alinhamento', 90.0));
        $this->itemRepository->shouldReceive('findById')->with('item-1')->once()->andReturn($this->makeItem());

        $this->serviceOrderRepository->shouldReceive('save')->once();
        $this->serviceOrderServiceRepository->shouldReceive('createServiceOrderService')->once();
        $this->serviceOrderItemRepository->shouldReceive('createServiceOrderItem')->once();

        $result = $this->useCase->execute($this->makeDTO());

        $this->assertSame('Alinhamento', $result->services[0]['name']);
        $this->assertSame(90.0, $result->services[0]['unit_price']);
    }

    public function test_snapshots_item_name_and_price_from_catalog(): void
    {
        $this->customerRepository->shouldReceive('findByDocument')->once()->andReturn($this->makeCustomer());
        $this->serviceRepository->shouldReceive('findById')->with('svc-1')->once()->andReturn($this->makeService());
        $this->itemRepository->shouldReceive('findById')->with('item-1')->once()->andReturn($this->makeItem('item-1', 50.0));

        $this->serviceOrderRepository->shouldReceive('save')->once();
        $this->serviceOrderServiceRepository->shouldReceive('createServiceOrderService')->once();
        $this->serviceOrderItemRepository->shouldReceive('createServiceOrderItem')->once();

        $result = $this->useCase->execute($this->makeDTO());

        $this->assertSame('Oil Filter', $result->items[0]['name']);
        $this->assertSame(50.0, $result->items[0]['unit_price']);
    }

    public function test_calculates_totals_correctly(): void
    {
        $this->customerRepository->shouldReceive('findByDocument')->once()->andReturn($this->makeCustomer());
        $this->serviceRepository->shouldReceive('findById')->with('svc-1')->once()->andReturn($this->makeService('svc-1', 'Oil', 200.0));
        $this->itemRepository->shouldReceive('findById')->with('item-1')->once()->andReturn($this->makeItem('item-1', 50.0));

        $this->serviceOrderRepository->shouldReceive('save')->once();
        $this->serviceOrderServiceRepository->shouldReceive('createServiceOrderService')->once();
        $this->serviceOrderItemRepository->shouldReceive('createServiceOrderItem')->once();

        $result = $this->useCase->execute($this->makeDTO([
            'services' => [['service_id' => 'svc-1', 'quantity' => 2.0]],
            'items' => [['item_id' => 'item-1', 'quantity' => 3.0]],
        ]));

        $this->assertSame(400.0, $result->servicesTotal);
        $this->assertSame(150.0, $result->itemsTotal);
        $this->assertSame(550.0, $result->totalBudget);
    }

    public function test_does_not_send_quote_when_flag_is_false(): void
    {
        $this->customerRepository->shouldReceive('findByDocument')->once()->andReturn($this->makeCustomer());
        $this->serviceRepository->shouldReceive('findById')->once()->andReturn($this->makeService());
        $this->itemRepository->shouldReceive('findById')->once()->andReturn($this->makeItem());

        $this->serviceOrderRepository->shouldReceive('save')->once();
        $this->serviceOrderServiceRepository->shouldReceive('createServiceOrderService')->once();
        $this->serviceOrderItemRepository->shouldReceive('createServiceOrderItem')->once();

        $result = $this->useCase->execute($this->makeDTO(['sendQuote' => false]));

        $this->assertSame(ServiceOrder::STATUS_RECEBIDA, $result->status);
        $this->assertNull($result->quoteSentAt);
    }

    public function test_sends_quote_when_flag_is_true(): void
    {
        $this->customerRepository->shouldReceive('findByDocument')->once()->andReturn($this->makeCustomer());
        $this->serviceRepository->shouldReceive('findById')->once()->andReturn($this->makeService());
        $this->itemRepository->shouldReceive('findById')->once()->andReturn($this->makeItem());

        $this->serviceOrderRepository->shouldReceive('save')->once();
        $this->serviceOrderServiceRepository->shouldReceive('createServiceOrderService')->once();
        $this->serviceOrderItemRepository->shouldReceive('createServiceOrderItem')->once();

        $result = $this->useCase->execute($this->makeDTO(['sendQuote' => true]));

        $this->assertSame(ServiceOrder::STATUS_AGUARDANDO_APROVACAO, $result->status);
        $this->assertNotNull($result->quoteSentAt);
    }

    public function test_throws_domain_exception_when_service_not_found(): void
    {
        $this->customerRepository->shouldReceive('findByDocument')->once()->andReturn($this->makeCustomer());
        $this->serviceRepository->shouldReceive('findById')->with('svc-missing')->once()->andReturnNull();
        $this->serviceOrderRepository->shouldNotReceive('save');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage("Servico 'svc-missing' nao encontrado.");

        $this->useCase->execute($this->makeDTO([
            'services' => [['service_id' => 'svc-missing', 'quantity' => 1.0]],
        ]));
    }

    public function test_throws_domain_exception_when_item_not_found(): void
    {
        $this->customerRepository->shouldReceive('findByDocument')->once()->andReturn($this->makeCustomer());
        $this->serviceRepository->shouldReceive('findById')->once()->andReturn($this->makeService());
        $this->itemRepository->shouldReceive('findById')->with('item-missing')->once()->andReturnNull();
        $this->serviceOrderRepository->shouldNotReceive('save');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage("Peca 'item-missing' nao encontrada.");

        $this->useCase->execute($this->makeDTO([
            'items' => [['item_id' => 'item-missing', 'quantity' => 1.0]],
        ]));
    }

    public function test_throws_exception_when_user_has_no_document(): void
    {
        $user = new User([
            'name' => 'No Doc',
            'email' => 'nodoc@test.com',
            'document' => null,
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Usuario autenticado sem documento vinculado');

        $this->useCase->execute($this->makeDTO(['user' => $user]));
    }

}
