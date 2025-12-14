<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('access_codes', function (Blueprint $table) {
            // Tornar user_id nullable (AccessCodes criados a partir de Bookings podem não ter user_id)
            // Nota: Para usar ->change(), é necessário ter doctrine/dbal instalado
            // Se não estiver instalado, use: DB::statement('ALTER TABLE access_codes MODIFY user_id BIGINT UNSIGNED NULL');
        });
        
        // Usar DB::statement para garantir compatibilidade
        \Illuminate\Support\Facades\DB::statement('ALTER TABLE access_codes MODIFY user_id BIGINT UNSIGNED NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('access_codes', function (Blueprint $table) {
            // Reverter user_id para NOT NULL (pode causar problemas se houver registros null)
            // Deixar comentado para evitar problemas em rollback
        });
    }
};
