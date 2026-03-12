<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tuya_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('place_id')->constrained()->cascadeOnDelete();
            $table->text('access_token');
            $table->text('refresh_token');
            $table->timestamp('expires_at');
            $table->string('uid')->index();
            $table->string('region', 50)->nullable();
            $table->timestamps();

            $table->unique('place_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tuya_credentials');
    }
};
