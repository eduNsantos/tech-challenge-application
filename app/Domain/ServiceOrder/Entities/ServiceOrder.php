<?php

namespace App\Domain\ServiceOrder\Entities;

use Illuminate\Support\Str;

class ServiceOrder
{
    public const STATUS_RECEBIDA = 'recebida';
    public const STATUS_EM_DIAGNOSTICO = 'em_diagnostico';
    public const STATUS_AGUARDANDO_APROVACAO = 'aguardando_aprovacao';
    public const STATUS_EM_EXECUCAO = 'em_execucao';
    public const STATUS_FINALIZADA = 'finalizada';
    public const STATUS_ENTREGUE = 'entregue';

    public function __construct(
        public string $id,
        public string $customerId,
        public string $vehicleId,
        public array $services,
        public array $items,
        public string $status,
        public float $servicesTotal,
        public float $itemsTotal,
        public float $totalBudget,
        public ?string $quoteSentAt,
        public ?string $quoteApprovedAt,
        public ?string $approvalToken = null
    ) {
        $this->assertStatus($status);
    }

    public static function create(
        string $customerId,
        string $vehicleId,
        array $services,
        array $items
    ): self {
        $servicesTotal = self::calculateItemsTotal($services);
        $itemsTotal = self::calculateItemsTotal($items);

        return new self(
            id: Str::uuid()->toString(),
            customerId: $customerId,
            vehicleId: $vehicleId,
            services: $services,
            items: $items,
            status: self::STATUS_RECEBIDA,
            servicesTotal: $servicesTotal,
            itemsTotal: $itemsTotal,
            totalBudget: $servicesTotal + $itemsTotal,
            quoteSentAt: null,
            quoteApprovedAt: null,
            approvalToken: null
        );
    }

    public function updateItems(?array $services, ?array $items): void
    {
        if ($services !== null) {
            $this->services = $services;
        }

        if ($items !== null) {
            $this->items = $items;
        }

        $this->servicesTotal = self::calculateItemsTotal($this->services);
        $this->itemsTotal = self::calculateItemsTotal($this->items);
        $this->totalBudget = $this->servicesTotal + $this->itemsTotal;
    }

    public function changeStatus(string $status): void
    {
        $this->assertStatus($status);
        $this->status = $status;
    }

    public function sendQuoteForApproval(): void
    {
        $this->status = self::STATUS_AGUARDANDO_APROVACAO;
        $this->quoteSentAt = now()->toDateTimeString();
        $this->approvalToken = bin2hex(random_bytes(32));
    }

    public function approveQuote(): void
    {
        $this->status = self::STATUS_EM_EXECUCAO;
        $this->quoteApprovedAt = now()->toDateTimeString();
        $this->approvalToken = null;
    }

    public static function allowedStatuses(): array
    {
        return [
            self::STATUS_RECEBIDA,
            self::STATUS_EM_DIAGNOSTICO,
            self::STATUS_AGUARDANDO_APROVACAO,
            self::STATUS_EM_EXECUCAO,
            self::STATUS_FINALIZADA,
            self::STATUS_ENTREGUE,
        ];
    }

    private function assertStatus(string $status): void
    {
        if (!in_array($status, self::allowedStatuses(), true)) {
            throw new \InvalidArgumentException('Status da OS invalido');
        }
    }

    private static function calculateItemsTotal(array $items): float
    {
        $total = 0.0;

        foreach ($items as $item) {
            $quantity = (float) ($item['quantity'] ?? 0);
            $unitPrice = (float) ($item['unit_price'] ?? 0);
            $total += $quantity * $unitPrice;
        }

        return round($total, 2);
    }
}
