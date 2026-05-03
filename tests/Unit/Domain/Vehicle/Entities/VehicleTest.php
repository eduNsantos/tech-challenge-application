<?php

namespace Tests\Unit\Domain\Vehicle\Entities;

use App\Domain\Vehicle\Entities\Vehicle;
use App\Domain\Vehicle\ValueObjects\Plate;
use PHPUnit\Framework\TestCase;

class VehicleTest extends TestCase
{
    private function makeVehicle(): Vehicle
    {
        return new Vehicle(
            id: 'uuid-1111',
            brand: 'Toyota',
            model: 'Corolla',
            year: 2023,
            plate: new Plate('ABC1D23')
        );
    }

    public function test_create_generates_uuid_and_sets_fields(): void
    {
        $vehicle = Vehicle::create(
            brand: 'Honda',
            model: 'Civic',
            year: 2025,
            plate: new Plate('DEF2G34')
        );

        $this->assertNotEmpty($vehicle->id);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $vehicle->id
        );
        $this->assertSame('Honda', $vehicle->brand);
        $this->assertSame('Civic', $vehicle->model);
        $this->assertSame(2025, $vehicle->year);
        $this->assertSame('DEF2G34', $vehicle->plate->getValue());
    }

    public function test_update_data_changes_only_non_null_fields(): void
    {
        $vehicle = $this->makeVehicle();
        $originalModel = $vehicle->model;
        $originalPlate = $vehicle->plate;

        $vehicle->updateData(
            brand: 'Nissan',
            model: null,
            year: 2026,
            plate: null
        );

        $this->assertSame('Nissan', $vehicle->brand);
        $this->assertSame($originalModel, $vehicle->model);
        $this->assertSame(2026, $vehicle->year);
        $this->assertSame($originalPlate, $vehicle->plate);
    }

    public function test_update_data_with_all_nulls_changes_nothing(): void
    {
        $vehicle = $this->makeVehicle();

        $vehicle->updateData(null, null, null, null);

        $this->assertSame('Toyota', $vehicle->brand);
        $this->assertSame('Corolla', $vehicle->model);
        $this->assertSame(2023, $vehicle->year);
        $this->assertSame('ABC1D23', $vehicle->plate->getValue());
    }
}
