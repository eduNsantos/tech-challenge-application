<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// O Services MS continua dono da execução, mas manda cada início/fim pela fila service-order-execution
// e a aplicação guarda uma cópia. Sem FK para users: o id vem do JWT do mecânico e uma FK quebrada
// travaria o grupo FIFO da O.S. inteira.
return new class extends Migration
{
    private const COLUMNS = ['started_at', 'started_user_id', 'finished_at', 'finished_user_id'];

    public function up(): void
    {
        Schema::table('service_order_services', function (Blueprint $table) {
            if (! Schema::hasColumn('service_order_services', 'started_at')) {
                $table->dateTime('started_at')->nullable();
            }
            if (! Schema::hasColumn('service_order_services', 'started_user_id')) {
                $table->unsignedBigInteger('started_user_id')->nullable();
            }
            if (! Schema::hasColumn('service_order_services', 'finished_at')) {
                $table->dateTime('finished_at')->nullable();
            }
            if (! Schema::hasColumn('service_order_services', 'finished_user_id')) {
                $table->unsignedBigInteger('finished_user_id')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('service_order_services', function (Blueprint $table) {
            $table->dropColumn(self::COLUMNS);
        });
    }
};
