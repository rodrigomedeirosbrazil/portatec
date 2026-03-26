<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_place', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->foreignId('place_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['device_id', 'place_id']);
        });

        $devicePlaces = DB::table('devices')
            ->whereNotNull('place_id')
            ->select(['id', 'place_id'])
            ->get()
            ->map(fn ($row) => [
                'device_id' => $row->id,
                'place_id' => $row->place_id,
                'created_at' => now(),
                'updated_at' => now(),
            ])
            ->values()
            ->all();

        if ($devicePlaces !== []) {
            DB::table('device_place')->insert($devicePlaces);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('device_place');
    }
};
