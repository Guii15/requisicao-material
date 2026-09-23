<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parametros', function (Blueprint $table) {
            $table->string('chave', 80)->primary();
            $table->string('valor');
            $table->timestamps();
        });

        $agora = now();
        DB::table('parametros')->insert([
            // Dias ÚTEIS contados a partir da data da solicitação (decisão de 23/09/2026).
            ['chave' => 'prazo_max_devolucao_dias', 'valor' => '3', 'created_at' => $agora, 'updated_at' => $agora],
            ['chave' => 'horas_confirmar_recebimento', 'valor' => '24', 'created_at' => $agora, 'updated_at' => $agora],
            ['chave' => 'exigir_liberador_diferente_do_separador', 'valor' => '1', 'created_at' => $agora, 'updated_at' => $agora],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('parametros');
    }
};
