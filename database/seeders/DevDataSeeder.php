<?php

namespace Database\Seeders;

use App\Application\Customer\DTOs\CreateCustomerDTO;
use App\Application\Customer\UseCases\CreateCustomerUseCase;
use App\Application\Item\DTOs\CreateItemDTO;
use App\Application\Item\DTOs\StockEntryDTO;
use App\Application\Item\UseCases\CreateItemUseCase;
use App\Application\Item\UseCases\StockEntryUseCase;
use App\Application\Service\DTOs\CreateServiceDTO;
use App\Application\Service\UseCases\CreateServiceUseCase;
use App\Application\ServiceOrder\DTOs\CreateServiceOrderDTO;
use App\Application\ServiceOrder\UseCases\CreateServiceOrderUseCase;
use App\Application\Vehicle\DTOs\CreateVehicleDTO;
use App\Application\Vehicle\UseCases\CreateVehicleUseCase;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DevDataSeeder extends Seeder
{
    public function run(): void
    {
        // ------------------------------------------------------------------ //
        // 1. Usuário
        // ------------------------------------------------------------------ //
        $email    = 'dev@example.com';
        $password = 'password';

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name'     => 'Dev User',
                'document' => '52998224725', // CPF válido
                'role'     => 'atendente',
                'password' => Hash::make($password),
            ]
        );

        // Autenticar para que os use cases que chamam Auth::id() funcionem
        Auth::guard('api')->login($user);
        $token = Auth::guard('api')->tokenById($user->id);

        // ------------------------------------------------------------------ //
        // 2. Serviço
        // ------------------------------------------------------------------ //
        /** @var CreateServiceUseCase $createService */
        $createService = app(CreateServiceUseCase::class);

        $service = $createService->execute(new CreateServiceDTO(
            name:  'Troca de óleo',
            price: '150.00',
        ));

        // ------------------------------------------------------------------ //
        // 3. Veículo
        // ------------------------------------------------------------------ //
        /** @var CreateVehicleUseCase $createVehicle */
        $createVehicle = app(CreateVehicleUseCase::class);

        $vehicle = $createVehicle->execute(new CreateVehicleDTO(
            brand: 'Toyota',
            model: 'Corolla',
            year:  2020,
            plate: 'DEV1D23',
        ));

        // ------------------------------------------------------------------ //
        // 4. Cliente
        // ------------------------------------------------------------------ //
        /** @var CreateCustomerUseCase $createCustomer */
        $createCustomer = app(CreateCustomerUseCase::class);

        $customer = $createCustomer->execute(new CreateCustomerDTO(
            name:     'Cliente Dev',
            email:    'cliente@example.com',
            phone:    '11999990000',
            document: '52998224725',
        ));

        // ------------------------------------------------------------------ //
        // 5. Item (peça) + entrada de estoque
        // ------------------------------------------------------------------ //
        /** @var CreateItemUseCase $createItem */
        $createItem = app(CreateItemUseCase::class);

        $item = $createItem->execute(new CreateItemDTO(
            name:            'Filtro de óleo',
            code:            'FLT-DEV-001',
            type:            'part',
            measureUnit:     'un',
            minimumQuantity: 2,
            description:     'Filtro para uso em seeder de dev',
            unitPrice:       35.00,
        ));

        /** @var StockEntryUseCase $stockEntry */
        $stockEntry = app(StockEntryUseCase::class);

        $stockEntry->execute(new StockEntryDTO(
            itemId:   $item->id,
            quantity: 100,
            reason:   'Estoque inicial (DevDataSeeder)',
            notes:    null,
        ));

        // ------------------------------------------------------------------ //
        // 6. Ordem de serviço
        // ------------------------------------------------------------------ //
        /** @var CreateServiceOrderUseCase $createOrder */
        $createOrder = app(CreateServiceOrderUseCase::class);

        $serviceOrder = $createOrder->execute(new CreateServiceOrderDTO(
            user:      $user,
            vehicleId: $vehicle->id,
            services:  [['service_id' => $service->id, 'quantity' => 1]],
            items:     [['item_id' => $item->id, 'quantity' => 2]],
            sendQuote: false,
        ));

        $serviceOrderServiceId = (string) (DB::table('service_order_services')
            ->where('service_order_id', $serviceOrder->id)
            ->where('service_id', $service->id)
            ->value('id') ?? 'COLE_O_ID_GERADO_PELO_DEVDATASEEDER');

        $this->updateBrunoFolderVars(
            vehicleId: $vehicle->id,
            serviceId: $service->id,
            itemId: $item->id,
            customerId: $customer->id,
            serviceOrderId: $serviceOrder->id,
            serviceOrderServiceId: $serviceOrderServiceId
        );

        // ------------------------------------------------------------------ //
        // Output
        // ------------------------------------------------------------------ //
        $this->command->newLine();
        $this->command->info('=== DevDataSeeder — dados criados ===');
        $this->command->table(
            ['Campo', 'Valor'],
            [
                ['Usuário e-mail',    $email],
                ['Usuário senha',     $password],
                ['JWT Token',         $token],
                ['Service ID',        $service->id],
                ['Vehicle ID',        $vehicle->id],
                ['Customer ID',       $customer->id],
                ['Item ID',           $item->id],
                ['Service Order ID',  $serviceOrder->id],
                ['Order Service ID',  $serviceOrderServiceId],
            ]
        );
        $this->command->newLine();
    }

    private function updateBrunoFolderVars(
        string $vehicleId,
        string $serviceId,
        string $itemId,
        string $customerId,
        string $serviceOrderId,
        string $serviceOrderServiceId
    ): void {
        $filePath = base_path('bruno/TechChallengeAuth/Authenticated/folder.bru');

        if (!is_file($filePath)) {
            return;
        }

        $content = file_get_contents($filePath);

        if ($content === false) {
            return;
        }

        $replacementBlock = "vars:pre-request {\n"
            . "  vehicleId: {$vehicleId}\n"
            . "  serviceId: {$serviceId}\n"
            . "  itemId: {$itemId}\n"
            . "  customerId: {$customerId}\n"
            . "  serviceOrderId: {$serviceOrderId}\n"
            . "  serviceOrderServiceId: {$serviceOrderServiceId}\n"
            . "  notificationId: COLE_O_ID_DA_NOTIFICACAO\n"
            . "}";

        $updated = preg_replace('/vars:pre-request\s*\{[\s\S]*?\}/', $replacementBlock, $content, 1);

        if ($updated === null) {
            return;
        }

        file_put_contents($filePath, $updated);
    }
}
