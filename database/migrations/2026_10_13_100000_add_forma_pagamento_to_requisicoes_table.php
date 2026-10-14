<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requisicoes', function (Blueprint $table) {
            // Pedido de compra: quem pede escolhe, a responsável pela compra pode trocar e informa as parcelas.
            $table->string('forma_pagamento')->nullable();
            $table->unsignedSmallInteger('parcelas')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('requisicoes', function (Blueprint $table) {
            $table->dropColumn(['forma_pagamento', 'parcelas']);
        });
    }
};
