<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_code_device_syncs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('access_code_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('external_reference')->nullable();
            $table->timestamp('synced_start')->nullable();
            $table->timestamp('synced_end')->nullable();
            $table->string('synced_pin', 6)->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->string('status')->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['access_code_id', 'device_id']);
            $table->index(['device_id', 'provider']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_code_device_syncs');
    }
};
