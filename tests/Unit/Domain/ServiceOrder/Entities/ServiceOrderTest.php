<?php

namespace Tests\Unit\Domain\ServiceOrder\Entities;

use App\Domain\ServiceOrder\Entities\ServiceOrder;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class ServiceOrderTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeServiceOrder(): ServiceOrder
    {
        return ServiceOrder::create(
            'cust-1',
            'veh-1',
            '52998224725',
            [['service_id' => 'svc-1', 'name' => 'Alinhamento', 'quantity' => 2.0, 'unit_price' => 100.0]],
            [['item_id' => 'item-1', 'name' => 'Filtro', 'quantity' => 3.0, 'unit_price' => 20.0]]
        );
    }

    public function test_create_generates_uuid_and_calculates_totals(): void
    {
        $serviceOrder = $this->makeServiceOrder();

        $this->assertNotEmpty($serviceOrder->id);
        $this->assertSame(ServiceOrder::STATUS_RECEBIDA, $serviceOrder->status);
        $this->assertSame(200.0, $serviceOrder->servicesTotal);
        $this->assertSame(60.0, $serviceOrder->itemsTotal);
        $this->assertSame(260.0, $serviceOrder->totalBudget);
    }

    public function test_update_items_recalculates_totals(): void
    {
        $serviceOrder = $this->makeServiceOrder();

        $serviceOrder->updateItems(
            [['service_id' => 'svc-2', 'name' => 'Balanceamento', 'quantity' => 1.0, 'unit_price' => 50.0]],
            []
        );

        $this->assertSame(50.0, $serviceOrder->servicesTotal);
        $this->assertSame(0.0, $serviceOrder->itemsTotal);
        $this->assertSame(50.0, $serviceOrder->totalBudget);
    }

    public function test_change_status_updates_status_when_valid(): void
    {
        $serviceOrder = $this->makeServiceOrder();

        $serviceOrder->changeStatus(ServiceOrder::STATUS_FINALIZADA);

        $this->assertSame(ServiceOrder::STATUS_FINALIZADA, $serviceOrder->status);
    }

    public function test_change_status_throws_when_invalid(): void
    {
        $serviceOrder = $this->makeServiceOrder();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Status da OS invalido');

        $serviceOrder->changeStatus('invalido');
    }

    public function test_send_quote_for_approval_sets_status_and_timestamp(): void
    {
        Carbon::setTestNow('2026-04-27 10:00:00');
        $serviceOrder = $this->makeServiceOrder();

        $serviceOrder->sendQuoteForApproval();

        $this->assertSame(ServiceOrder::STATUS_AGUARDANDO_APROVACAO, $serviceOrder->status);
        $this->assertSame('2026-04-27 10:00:00', $serviceOrder->quoteSentAt);
    }

    public function test_send_quote_for_approval_generates_approval_token(): void
    {
        $serviceOrder = $this->makeServiceOrder();

        $serviceOrder->sendQuoteForApproval();

        $this->assertNotNull($serviceOrder->approvalToken);
        $this->assertSame(64, strlen($serviceOrder->approvalToken));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $serviceOrder->approvalToken);
    }

    public function test_approve_quote_sets_status_and_timestamp(): void
    {
        Carbon::setTestNow('2026-04-27 11:00:00');
        $serviceOrder = $this->makeServiceOrder();

        $serviceOrder->approveQuote();

        $this->assertSame(ServiceOrder::STATUS_EM_EXECUCAO, $serviceOrder->status);
        $this->assertSame('2026-04-27 11:00:00', $serviceOrder->quoteApprovedAt);
    }

    public function test_approve_quote_clears_approval_token(): void
    {
        $serviceOrder = $this->makeServiceOrder();
        $serviceOrder->sendQuoteForApproval();

        $this->assertNotNull($serviceOrder->approvalToken);

        $serviceOrder->approveQuote();

        $this->assertNull($serviceOrder->approvalToken);
    }

    public function test_create_initializes_approval_token_as_null(): void
    {
        $serviceOrder = $this->makeServiceOrder();

        $this->assertNull($serviceOrder->approvalToken);
    }
}