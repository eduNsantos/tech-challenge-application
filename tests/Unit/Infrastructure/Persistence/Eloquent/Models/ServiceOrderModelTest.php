<?php

namespace Tests\Unit\Infrastructure\Persistence\Eloquent\Models;

use App\Infrastructure\Persistence\Eloquent\Models\ServiceOrderModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\TestCase;

class ServiceOrderModelTest extends TestCase
{
    public function test_model_configuration_is_correct(): void
    {
        $model = new ServiceOrderModel();

        $this->assertSame('service_orders', $model->getTable());
        $this->assertFalse($model->getIncrementing());
        $this->assertSame('string', $model->getKeyType());
    }

    public function test_customer_relation_is_belongs_to(): void
    {
        $model = new ServiceOrderModel();
        $relation = $model->customer();

        $this->assertInstanceOf(BelongsTo::class, $relation);
    }

    public function test_vehicle_relation_is_belongs_to(): void
    {
        $model = new ServiceOrderModel();
        $relation = $model->vehicle();

        $this->assertInstanceOf(BelongsTo::class, $relation);
    }
}
