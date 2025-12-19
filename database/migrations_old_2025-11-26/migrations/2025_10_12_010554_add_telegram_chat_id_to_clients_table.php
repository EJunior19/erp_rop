<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            // Guardamos el chat id como string por compatibilidad (grupos pueden ser negativos)
            if (!Schema::hasColumn('clients', 'telegram_chat_id')) {
                $table->string('telegram_chat_id', 32)->nullable()->after('email');
            }

            // Índice para búsquedas por chat id (cambia a unique() si querés forzar unicidad)
            if (!$this->indexExists('clients', 'clients_telegram_chat_id_index')) {
                $table->index('telegram_chat_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (Schema::hasColumn('clients', 'telegram_chat_id')) {
                // primero dropear índice si existe (MySQL permite dropIndex con nombre o con columnas)
                try { $table->dropIndex('clients_telegram_chat_id_index'); } catch (\Throwable $e) {}
                try { $table->dropIndex(['telegram_chat_id']); } catch (\Throwable $e) {}
                $table->dropColumn('telegram_chat_id');
            }
        });
    }

    // Helper para verificar índice (evita errores si corres migraciones en entornos distintos)
    private function indexExists(string $table, string $indexName): bool
    {
        try {
            return collect(Schema::getIndexes($table) ?? [])->contains(function($idx) use ($indexName) {
                return isset($idx['name']) && $idx['name'] === $indexName;
            });
        } catch (\Throwable $e) {
            return false;
        }
    }
};
