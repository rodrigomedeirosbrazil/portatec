<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('place_device_functions')) {
            return;
        }

        // As duas tabelas são escritas juntas hoje, mas nada no banco garante
        // isso. Um dispositivo que só constasse na tabela antiga perderia o
        // vínculo com o local em silêncio — daí o backfill antes do drop.
        $pairs = DB::table('place_device_functions')
            ->join('device_functions', 'device_functions.id', '=', 'place_device_functions.device_function_id')
            ->select('place_device_functions.place_id', 'device_functions.device_id')
            ->distinct()
            ->get();

        foreach ($pairs as $pair) {
            $exists = DB::table('device_place')
                ->where('place_id', $pair->place_id)
                ->where('device_id', $pair->device_id)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('device_place')->insert([
                'place_id' => $pair->place_id,
                'device_id' => $pair->device_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::drop('place_device_functions');
    }

    public function down(): void
    {
        Schema::create('place_device_functions', function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->id();
            $table->foreignId('place_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_function_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }
};
