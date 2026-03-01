<?php

use App\Enums\DeviceBrandEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('place_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->enum('brand', array_map(
                static fn (DeviceBrandEnum $brand): string => $brand->value,
                DeviceBrandEnum::cases()
            ))->default(DeviceBrandEnum::Portatec->value);
            $table->string('external_device_id')->nullable()->index();
            $table->string('default_pin', 6)->nullable();
            $table->timestamp('last_sync')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
