<?php

namespace App\Http\Controllers;

use CriarVeicularUseCase;

class VeiculoController extends Controller
{
    public function criar()
    {
        $useCase = new CriarVeicularUseCase();
        $useCase->execute();

        return response()->json(['message' => 'Veículo criado com sucesso']);
    }
}

