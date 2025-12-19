<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('credits', function (Blueprint $table) {
            if (!Schema::hasColumn('credits', 'auto_overdue')) {
                $table->boolean('auto_overdue')->default(true)->after('status');
            }
            if (!Schema::hasColumn('credits', 'last_notified_at')) {
                $table->date('last_notified_at')->nullable()->after('due_date');
            }
            if (!Schema::hasColumn('credits', 'next_notify_at')) {
                $table->date('next_notify_at')->nullable()->after('last_notified_at');
            }
            if (!Schema::hasColumn('credits', 'notify_every_days')) {
                $table->unsignedSmallInteger('notify_every_days')->default(7)->after('next_notify_at');
            }

            // Índices recomendados para filtros frecuentes
            try { $table->index(['due_date', 'status'], 'credits_due_status_idx'); } catch (\Throwable $e) {}
            try { $table->index(['next_notify_at', 'last_notified_at'], 'credits_notify_dates_idx'); } catch (\Throwable $e) {}
        });

        // Backfill (opcional, seguro): agenda notificación suave para pendientes
        try {
            DB::table('credits')
                ->where('status', 'pendiente')
                ->whereNull('next_notify_at')
                ->update(['next_notify_at' => now()->startOfDay()->addDays(3)]);
        } catch (\Throwable $e) {
            // ignorar en entornos sin NOW() o sin permisos
        }
    }

    public function down(): void
    {
        Schema::table('credits', function (Blueprint $table) {
            // Drop índices si existen
            try { $table->dropIndex('credits_due_status_idx'); } catch (\Throwable $e) {}
            try { $table->dropIndex('credits_notify_dates_idx'); } catch (\Throwable $e) {}

            foreach (['auto_overdue','last_notified_at','next_notify_at','notify_every_days'] as $col) {
                if (Schema::hasColumn('credits', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
