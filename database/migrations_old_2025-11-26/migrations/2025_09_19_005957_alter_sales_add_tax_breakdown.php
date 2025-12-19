<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('sales', function (Blueprint $t) {
            // totales por tipo de IVA + resumen
            if (!Schema::hasColumn('sales','gravada_10')) $t->decimal('gravada_10', 14, 2)->default(0);
            if (!Schema::hasColumn('sales','iva_10'))     $t->decimal('iva_10',     14, 2)->default(0);
            if (!Schema::hasColumn('sales','gravada_5'))  $t->decimal('gravada_5',  14, 2)->default(0);
            if (!Schema::hasColumn('sales','iva_5'))      $t->decimal('iva_5',      14, 2)->default(0);
            if (!Schema::hasColumn('sales','exenta'))     $t->decimal('exenta',     14, 2)->default(0);
            if (!Schema::hasColumn('sales','total_iva'))  $t->decimal('total_iva',  14, 2)->default(0);
        });
    }

    public function down(): void {
        Schema::table('sales', function (Blueprint $t) {
            if (Schema::hasColumn('sales','gravada_10')) $t->dropColumn('gravada_10');
            if (Schema::hasColumn('sales','iva_10'))     $t->dropColumn('iva_10');
            if (Schema::hasColumn('sales','gravada_5'))  $t->dropColumn('gravada_5');
            if (Schema::hasColumn('sales','iva_5'))      $t->dropColumn('iva_5');
            if (Schema::hasColumn('sales','exenta'))     $t->dropColumn('exenta');
            if (Schema::hasColumn('sales','total_iva'))  $t->dropColumn('total_iva');
        });
    }
};
