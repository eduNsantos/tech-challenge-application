<?php

namespace App\Interfaces\Http\Controllers;

use Illuminate\Http\Request;
use App\Application\Vehicle\UseCases\CreateVehicleUseCase;
use App\Application\Vehicle\DTOs\CreateVehicleDTO;
use App\Http\Requests\Interfaces\Http\Requests\CreateVehicleRequest;

class VehicleController
{
    public function store(CreateVehicleRequest $request, CreateVehicleUseCase $useCase)
    {
        $dto = new CreateVehicleDTO(
            $request->brand,
            $request->model,
            $request->year,
            $request->plate
        );

        $vehicle = $useCase->execute($dto);

        return response()->json([
            'id' => $vehicle->id,
            'message' => 'Veículo cadastrado com sucesso'
        ]);
    }
}
