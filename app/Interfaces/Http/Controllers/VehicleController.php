<?php

namespace App\Interfaces\Http\Controllers;

use App\Application\Vehicle\UseCases\CreateVehicleUseCase;
use App\Application\Vehicle\DTOs\CreateVehicleDTO;
use App\Application\Vehicle\DTOs\ListVehicleDTO;
use App\Application\Vehicle\UseCases\ListVehicleUseCase;
use App\Interfaces\Http\Requests\CreateVehicleRequest;
use App\Interfaces\Http\Requests\ListVehicleRequest;

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

    public function list(ListVehicleRequest $request, ListVehicleUseCase $useCase)
    {

        $dto = new ListVehicleDTO(
            page: $request->input('page', 1),
            perPage: $request->input('perPage', 10)
        );

        $vehicles = $useCase->execute($dto);

        return response()->json($vehicles);
    }
}
