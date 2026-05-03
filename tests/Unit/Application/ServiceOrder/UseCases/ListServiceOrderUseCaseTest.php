<?php

namespace Tests\Unit\Application\ServiceOrder\UseCases;

use App\Application\ServiceOrder\DTOs\ListServiceOrderDTO;
use App\Application\ServiceOrder\UseCases\ListServiceOrderUseCase;
use App\Domain\ServiceOrder\Interfaces\ServiceOrderRepositoryInterface;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class ListServiceOrderUseCaseTest extends TestCase
{
    private MockInterface $repository;
    private ListServiceOrderUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(ServiceOrderRepositoryInterface::class);
        $this->useCase = new ListServiceOrderUseCase($this->repository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_calls_paginate_with_given_values(): void
    {
        $paginated = ['data' => [], 'page' => 2, 'perPage' => 20];

        $this->repository->shouldReceive('paginate')->once()->with(2, 20)->andReturn($paginated);

        $result = $this->useCase->execute(new ListServiceOrderDTO(2, 20));

        $this->assertSame($paginated, $result);
    }

    public function test_defaults_per_page_to_10_when_invalid(): void
    {
        $this->repository->shouldReceive('paginate')->once()->with(1, 10)->andReturn(['data' => []]);

        $result = $this->useCase->execute(new ListServiceOrderDTO(1, 0));

        $this->assertSame(['data' => []], $result);
    }
}