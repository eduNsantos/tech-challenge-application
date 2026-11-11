<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_orders', function (Blueprint $table) {
            // order_id enviado ao payment-service (um por envio de orçamento)
            $table->uuid('payment_order_id')->nullable()->unique()->after('approval_token');
            // segredo embutido no callback_url — autentica o retorno do payment-service
            $table->string('payment_callback_token', 64)->nullable()->unique()->after('payment_order_id');
            $table->text('payment_link')->nullable()->after('payment_callback_token');
        });
    }

    public function down(): void
    {
        Schema::table('service_orders', function (Blueprint $table) {
            $table->dropUnique(['payment_order_id']);
            $table->dropUnique(['payment_callback_token']);
            $table->dropColumn(['payment_order_id', 'payment_callback_token', 'payment_link']);
        });
    }
};
