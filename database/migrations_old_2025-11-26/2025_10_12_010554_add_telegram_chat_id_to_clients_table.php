<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Migración vacía porque la lógica de Telegram
        // se maneja en 2025_10_12_111327_add_telegram_columns_to_clients_table.php
    }

    public function down(): void
    {
        // No hacemos nada acá tampoco.
    }
};
