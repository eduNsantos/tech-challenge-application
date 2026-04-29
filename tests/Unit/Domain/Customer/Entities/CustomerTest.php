<?php

namespace Tests\Unit\Domain\Customer\Entities;

use App\Domain\Customer\Entities\Customer;
use PHPUnit\Framework\TestCase;

class CustomerTest extends TestCase
{
    public function test_create_returns_customer_with_generated_uuid(): void
    {
        $customer = Customer::create('João Silva', 'joao@example.com', '11999990000', '52998224725');

        $this->assertNotEmpty($customer->id);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $customer->id
        );
    }

    public function test_create_assigns_all_fields(): void
    {
        $customer = Customer::create('Maria', 'maria@example.com', '11988880000', '52998224725');

        $this->assertSame('Maria', $customer->name);
        $this->assertSame('maria@example.com', $customer->email);
        $this->assertSame('11988880000', $customer->phone);
        $this->assertSame('52998224725', $customer->document);
    }

    public function test_update_data_updates_only_provided_fields(): void
    {
        $customer = Customer::create('João', 'joao@example.com', '11999990000', '52998224725');

        $customer->updateData('João Atualizado', null, null, null);

        $this->assertSame('João Atualizado', $customer->name);
        $this->assertSame('joao@example.com', $customer->email);
        $this->assertSame('11999990000', $customer->phone);
        $this->assertSame('52998224725', $customer->document);
    }

    public function test_update_data_updates_all_fields(): void
    {
        $customer = Customer::create('Antigo', 'antigo@example.com', '11000000000', '52998224725');

        $customer->updateData('Novo', 'novo@example.com', '11111111111', '73127709006');

        $this->assertSame('Novo', $customer->name);
        $this->assertSame('novo@example.com', $customer->email);
        $this->assertSame('11111111111', $customer->phone);
        $this->assertSame('73127709006', $customer->document);
    }

    public function test_update_data_ignores_null_values(): void
    {
        $customer = Customer::create('Mantido', 'mantido@example.com', '11222220000', '52998224725');

        $customer->updateData(null, null, null, null);

        $this->assertSame('Mantido', $customer->name);
        $this->assertSame('mantido@example.com', $customer->email);
        $this->assertSame('11222220000', $customer->phone);
        $this->assertSame('52998224725', $customer->document);
    }
}
