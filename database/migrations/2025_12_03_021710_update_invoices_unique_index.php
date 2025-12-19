<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // 🔥 eliminar índice único actual SOLO sobre "number"
            $table->dropUnique('invoices_number_unique');

            // ✅ crear índice único compuesto (series + number)
            $table->unique(['series', 'number'], 'invoices_series_number_unique');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique('invoices_series_number_unique');

            $table->unique('number', 'invoices_number_unique');
        });
    }
};
