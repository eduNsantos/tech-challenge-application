<?php

namespace App\Http\Controllers;


abstract class VeiculoController
{
    public function criar()
    {
        $useCase = CriarVeicularUseCase();
        $useCase->execute();

        return response()->json(['message' => 'Veículo criado com sucesso']);
    }
}
