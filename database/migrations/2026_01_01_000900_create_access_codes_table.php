<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('place_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('pin', 6);
            $table->string('label')->nullable();
            $table->timestamp('start');
            $table->timestamp('end')->nullable();
            $table->timestamps();

            $table->index(['place_id', 'pin']);
            $table->index(['start', 'end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_codes');
    }
};
