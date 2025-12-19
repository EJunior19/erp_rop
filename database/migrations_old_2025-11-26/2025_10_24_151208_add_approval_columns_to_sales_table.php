<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            // Ajustá el tipo según tu users.id (bigint por defecto en Laravel)
            $table->foreignId('approved_by')->nullable()
                  ->constrained('users')->nullOnDelete();

            $table->timestamp('approved_at')->nullable();

            // Opcional: índices para filtros
            $table->index('approved_at');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropIndex(['approved_at']);
            $table->dropColumn(['approved_by', 'approved_at']);
        });
    }
};
