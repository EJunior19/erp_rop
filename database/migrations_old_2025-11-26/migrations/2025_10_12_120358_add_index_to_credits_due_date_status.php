<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // 1) Agregar columnas SOLO si faltan
        Schema::table('clients', function (Blueprint $table) {
            if (!Schema::hasColumn('clients', 'telegram_chat_id')) {
                $table->unsignedBigInteger('telegram_chat_id')->nullable()->after('phone');
            }
            if (!Schema::hasColumn('clients', 'telegram_link_token')) {
                $table->string('telegram_link_token', 64)->nullable()->after('telegram_chat_id');
            }
            if (!Schema::hasColumn('clients', 'telegram_linked_at')) {
                $table->timestamp('telegram_linked_at')->nullable()->after('telegram_link_token');
            }
        });

        // 2) Unicidad (PostgreSQL): usar índices únicos con IF NOT EXISTS
        //    (evita chocar con nombres de constraints diferentes)
        try {
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS clients_telegram_chat_id_unique_idx ON clients (telegram_chat_id) WHERE telegram_chat_id IS NOT NULL;');
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS clients_telegram_link_token_unique_idx ON clients (telegram_link_token) WHERE telegram_link_token IS NOT NULL;');
        } catch (\Throwable $e) {
            // ignorar si no aplica
        }
    }

    public function down(): void
    {
        // Borrar índices si existen (PostgreSQL)
        try {
            DB::statement('DROP INDEX IF EXISTS clients_telegram_chat_id_unique_idx;');
            DB::statement('DROP INDEX IF EXISTS clients_telegram_link_token_unique_idx;');
        } catch (\Throwable $e) {
            // ignorar
        }

        // Borrar columnas SOLO si existen
        Schema::table('clients', function (Blueprint $table) {
            if (Schema::hasColumn('clients', 'telegram_linked_at')) {
                $table->dropColumn('telegram_linked_at');
            }
            if (Schema::hasColumn('clients', 'telegram_link_token')) {
                $table->dropColumn('telegram_link_token');
            }
            if (Schema::hasColumn('clients', 'telegram_chat_id')) {
                $table->dropColumn('telegram_chat_id');
            }
        });
    }
};
