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
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type');
            $table->string('topic')->nullable();
            $table->string('command_topic')->nullable();
            $table->string('payload_on')->nullable();
            $table->string('payload_off')->nullable();
            $table->string('json_attribute')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();

            $table->index(['topic']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
