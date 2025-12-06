<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Para SQLite, precisamos recriar as tabelas porque não suporta ALTER TABLE para foreign keys.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            $this->migrateForSqlite();
        } else {
            $this->migrateForOtherDatabases();
        }
    }

    /**
     * Migration para SQLite - recria as tabelas com cascade delete
     */
    protected function migrateForSqlite(): void
    {
        // Desabilitar temporariamente foreign keys para poder dropar as tabelas
        DB::statement('PRAGMA foreign_keys = OFF;');

        // Recriar place_users com cascade delete
        DB::statement('
            CREATE TABLE place_users_new (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                place_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                role VARCHAR(255) NOT NULL,
                created_at DATETIME,
                updated_at DATETIME,
                UNIQUE(place_id, user_id),
                FOREIGN KEY (place_id) REFERENCES places(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ');

        DB::statement('INSERT INTO place_users_new SELECT * FROM place_users');
        DB::statement('DROP TABLE place_users');
        DB::statement('ALTER TABLE place_users_new RENAME TO place_users');

        // Recriar place_device_functions com cascade delete
        DB::statement('
            CREATE TABLE place_device_functions_new (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                place_id INTEGER NOT NULL,
                device_function_id INTEGER NOT NULL,
                created_at DATETIME,
                updated_at DATETIME,
                FOREIGN KEY (place_id) REFERENCES places(id) ON DELETE CASCADE,
                FOREIGN KEY (device_function_id) REFERENCES device_functions(id) ON DELETE CASCADE
            )
        ');

        DB::statement('INSERT INTO place_device_functions_new SELECT * FROM place_device_functions');
        DB::statement('DROP TABLE place_device_functions');
        DB::statement('ALTER TABLE place_device_functions_new RENAME TO place_device_functions');

        // Reabilitar foreign keys
        DB::statement('PRAGMA foreign_keys = ON;');
    }

    /**
     * Migration para MySQL/PostgreSQL - altera as foreign keys diretamente
     */
    protected function migrateForOtherDatabases(): void
    {
        Schema::table('place_users', function (Blueprint $table) {
            $table->dropForeign(['place_id']);
        });

        Schema::table('place_users', function (Blueprint $table) {
            $table->foreign('place_id')
                ->references('id')
                ->on('places')
                ->onDelete('cascade');
        });

        Schema::table('place_device_functions', function (Blueprint $table) {
            $table->dropForeign(['place_id']);
        });

        Schema::table('place_device_functions', function (Blueprint $table) {
            $table->foreign('place_id')
                ->references('id')
                ->on('places')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            // Para reverter no SQLite, precisaríamos recriar as tabelas sem cascade
            // Por simplicidade, vamos apenas deixar um aviso
            throw new \Exception('Rollback not supported for SQLite. Please restore from backup if needed.');
        } else {
            Schema::table('place_users', function (Blueprint $table) {
                $table->dropForeign(['place_id']);
            });

            Schema::table('place_users', function (Blueprint $table) {
                $table->foreign('place_id')
                    ->references('id')
                    ->on('places');
            });

            Schema::table('place_device_functions', function (Blueprint $table) {
                $table->dropForeign(['place_id']);
            });

            Schema::table('place_device_functions', function (Blueprint $table) {
                $table->foreign('place_id')
                    ->references('id')
                    ->on('places');
            });
        }
    }
};
