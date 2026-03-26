<?php

namespace App\Interfaces\Http\Controllers;

use Illuminate\Http\Request;
use App\Application\Vehicle\UseCases\CreateVehicleUseCase;
use App\Application\Vehicle\DTOs\CreateVehicleDTO;
use App\Application\Vehicle\UseCases\ListVehicleUseCase;
use App\Interfaces\Http\Requests\CreateVehicleRequest;

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

    public function list(Request $request, ListVehicleUseCase $useCase)
    {
        $vehicles = $useCase->execute();

        return response()->json($vehicles);
    }
}
