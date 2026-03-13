<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_code_tuya_passwords', function (Blueprint $table) {
            $table->id();
            $table->foreignId('access_code_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('tuya_password_id');
            $table->timestamps();

            $table->unique(['access_code_id', 'device_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_code_tuya_passwords');
    }
};
