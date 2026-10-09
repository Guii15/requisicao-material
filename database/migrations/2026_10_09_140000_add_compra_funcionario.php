<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Quem aprova as compras de funcionário (Kelber, Sérgio e Miguel).
            $table->boolean('aprova_compras')->default(false);
        });

        Schema::table('requisicoes', function (Blueprint $table) {
            $table->foreignId('compra_decidido_por_id')->nullable()->constrained('users');
            $table->timestamp('compra_decidido_em')->nullable();
            $table->text('compra_observacao')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('requisicoes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('compra_decidido_por_id');
            $table->dropColumn(['compra_decidido_em', 'compra_observacao']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('aprova_compras');
        });
    }
};
