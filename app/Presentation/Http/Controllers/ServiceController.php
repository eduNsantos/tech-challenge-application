<?php

namespace App\Presentation\Http\Controllers;

use App\Application\Service\DTOs\ListServiceDTO;
use App\Application\Service\UseCases\ListServiceUseCase;
use Illuminate\Http\Request;

class ServiceController
{

    public function list(Request $request, ListServiceUseCase $useCase)
    {
        $dto = new ListServiceDTO((int) $request->query('page'), (int) $request->query('per_page'));

        $services = $useCase->execute($dto);

        return response()->json($services);
    }


    // public function store(CreateServiceRequest $request, CreateServiceUseCase $useCase)
    // {
    //     $dto = new CreateServiceDTO(
    //         $request->name,
    //         $request->price
    //     );

    //     $service = $useCase->execute($dto);

    //     return response()->json([
    //         'id' => $service->id,
    //         'message' => 'Serviço cadastrado com sucesso'
    //     ]);
    // }

    // public function show(string $id, ShowServiceUseCase $useCase)
    // {
    //     $dto = new ShowServiceDTO(
    //         id: $id
    //     );

    //     $service = $useCase->execute($dto);

    //     return response()->json($service);
    // }

    // public function update(UpdateServiceRequest $request, UpdateServiceUseCase $useCase)
    // {
    //     $dto = new UpdateServiceDTO(
    //         id: $request->route('id'),
    //         name: $request->input('name'),
    //         price: $request->input('price')
    //     );

    //     $service = $useCase->execute($dto);

    //     return response()->json([
    //         'service' => [
    //             'id' => $service->id,
    //             'name' => $service->name,
    //             'price' => $service->price,
    //         ],
    //         'message' => 'Serviço atualizado com sucesso'
    //     ], 200);
    // }
}
