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
        Schema::rename('access_pins', 'access_codes');

        // Nota: Foreign keys em outras tabelas que referenciam access_pins
        // devem ser atualizadas em migrations separadas
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::rename('access_codes', 'access_pins');
    }
};
