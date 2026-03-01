<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('place_device_functions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('place_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_function_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['place_id', 'device_function_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('place_device_functions');
    }
};
