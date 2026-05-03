<?php

namespace Tests\Unit\Domain\Vehicle\ValueObjects;

use App\Domain\Vehicle\ValueObjects\Plate;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PlateTest extends TestCase
{
    #[DataProvider('validPlates')]
    public function test_accepts_valid_plate_formats(string $input): void
    {
        $plate = new Plate($input);

        $this->assertSame($input, $plate->getValue());
    }

    public static function validPlates(): array
    {
        return [
            'mercosul format' => ['ABC1D23'],
            'old format also accepted by regex' => ['ABC1234'],
        ];
    }

    #[DataProvider('invalidPlates')]
    public function test_rejects_invalid_plate_formats(string $input): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Placa inválida');

        new Plate($input);
    }

    public static function invalidPlates(): array
    {
        return [
            'too short' => ['AB12345'],
            'with hyphen' => ['ABC-1234'],
            'lowercase' => ['abc1d23'],
            'invalid chars' => ['AB@1D23'],
            'empty' => [''],
        ];
    }
}
