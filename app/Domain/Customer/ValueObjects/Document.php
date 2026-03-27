<?php

namespace App\Domain\Customer\ValueObjects;

use InvalidArgumentException;

class Document
{
    private string $value;

    public function __construct(string $document)
    {
        $cpfCnpj = preg_replace('/[^0-9]/', '', $document);
        $length = strlen($cpfCnpj);
        if ($length === 11) {
            $this->validateCPF($cpfCnpj);
        } elseif ($length === 14) {
            $this->validateCNPJ($cpfCnpj);
        } else {
            throw new InvalidArgumentException('Documento inválido');
        }
        if (!preg_match('/^[0-9]{11}$/', $document)) {
            throw new InvalidArgumentException('Documento inválido');
        }

        $this->value = $document;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    private function validateCPF(string $cpf): void
    {
        $cpf = preg_replace('/[^0-9]/', '', $cpf);
        if (strlen($cpf) !== 11 || preg_match('/(\d)\1{10}/', $cpf)) {
            throw new InvalidArgumentException('CPF inválido');
        }

        for ($t = 9; $t < 11; $t++) {
            for ($d = 0, $c = 0; $c < $t; $c++) {
                $d += $cpf[$c] * (($t + 1) - $c);
            }
            $d = ((10 * $d) % 11) % 10;
            if ($cpf[$c] != $d) {
                throw new InvalidArgumentException('CPF inválido');
            }
        }
    }

    private function validateCNPJ(string $cnpj): void
    {
        $cnpj = preg_replace('/[^0-9]/', '', $cnpj);
        if (strlen($cnpj) !== 14 || preg_match('/(\d)\1{13}/', $cnpj)) {
            throw new InvalidArgumentException('CNPJ inválido');
        }
    
        // Algoritmo de validação de CNPJ
        for ($i = 0, $j = 5, $soma = 0; $i < 12; $i++) {
            $soma += $cnpj[$i] * $j;
            $j = ($j == 2) ? 9 : $j - 1;
        }
        $resto = $soma % 11;
        if ($cnpj[12] != ($resto < 2 ? 0 : 11 - $resto)) {
            throw new InvalidArgumentException('CNPJ inválido');
        }
    
        for ($i = 0, $j = 6, $soma = 0; $i < 13; $i++) {
            $soma += $cnpj[$i] * $j;
            $j = ($j == 2) ? 9 : $j - 1;
        }
        $resto = $soma % 11;
        if ($cnpj[13] != ($resto < 2 ? 0 : 11 - $resto)) {
            throw new InvalidArgumentException('CNPJ inválido');
        }
    }
}