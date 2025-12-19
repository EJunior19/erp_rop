<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (!Schema::hasColumn('payments', 'reference')) {
                $table->string('reference', 100)->nullable()->after('method');
            }
            if (!Schema::hasColumn('payments', 'note')) {
                $table->string('note', 500)->nullable()->after('reference');
            }
            if (!Schema::hasColumn('payments', 'user_id')) {
                $table->foreignId('user_id')->nullable()->after('note')
                      ->constrained()->nullOnDelete();
            }

            // Índice compuesto útil para reportes/orden
            // (algunos drivers no exponen getIndexes; si falla, ignora silenciosamente)
            try {
                $table->index(['credit_id', 'payment_date', 'id'], 'payments_credit_date_idx');
            } catch (\Throwable $e) {}
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // limpiar índice compuesto si existe
            try { $table->dropIndex('payments_credit_date_idx'); } catch (\Throwable $e) {}

            if (Schema::hasColumn('payments', 'user_id')) {
                $table->dropConstrainedForeignId('user_id');
            }
            if (Schema::hasColumn('payments', 'note')) {
                $table->dropColumn('note');
            }
            if (Schema::hasColumn('payments', 'reference')) {
                $table->dropColumn('reference');
            }
        });
    }
};
