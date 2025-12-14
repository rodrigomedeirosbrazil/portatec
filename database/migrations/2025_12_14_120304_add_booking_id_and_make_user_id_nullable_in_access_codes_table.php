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
        // SQLite não suporta MODIFY diretamente, então precisamos recriar a tabela
        if (config('database.default') === 'sqlite') {
            // Para SQLite, recriar a tabela com user_id nullable
            Schema::table('access_codes', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
            });
            
            \Illuminate\Support\Facades\DB::statement('
                CREATE TABLE access_codes_new (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    place_id INTEGER NOT NULL,
                    user_id INTEGER NULL,
                    pin VARCHAR(6) NOT NULL,
                    start DATETIME NOT NULL,
                    end DATETIME NOT NULL,
                    created_at DATETIME,
                    updated_at DATETIME,
                    FOREIGN KEY (place_id) REFERENCES places(id) ON DELETE CASCADE,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                )
            ');
            
            \Illuminate\Support\Facades\DB::statement('INSERT INTO access_codes_new SELECT * FROM access_codes');
            \Illuminate\Support\Facades\DB::statement('DROP TABLE access_codes');
            \Illuminate\Support\Facades\DB::statement('ALTER TABLE access_codes_new RENAME TO access_codes');
        } else {
            // Para MySQL/PostgreSQL, usar ->change() (requer doctrine/dbal)
            Schema::table('access_codes', function (Blueprint $table) {
                $table->foreignId('user_id')->nullable()->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverter user_id para NOT NULL (pode causar problemas se houver registros null)
        if (config('database.default') === 'sqlite') {
            Schema::table('access_codes', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
            });
            
            \Illuminate\Support\Facades\DB::statement('
                CREATE TABLE access_codes_old (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    place_id INTEGER NOT NULL,
                    user_id INTEGER NOT NULL,
                    pin VARCHAR(6) NOT NULL,
                    start DATETIME NOT NULL,
                    end DATETIME NOT NULL,
                    created_at DATETIME,
                    updated_at DATETIME,
                    FOREIGN KEY (place_id) REFERENCES places(id) ON DELETE CASCADE,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                )
            ');
            
            // Copiar apenas registros que têm user_id (filtrar NULLs)
            \Illuminate\Support\Facades\DB::statement('INSERT INTO access_codes_old SELECT * FROM access_codes WHERE user_id IS NOT NULL');
            \Illuminate\Support\Facades\DB::statement('DROP TABLE access_codes');
            \Illuminate\Support\Facades\DB::statement('ALTER TABLE access_codes_old RENAME TO access_codes');
        } else {
            Schema::table('access_codes', function (Blueprint $table) {
                $table->foreignId('user_id')->nullable(false)->change();
            });
        }
    }
};
