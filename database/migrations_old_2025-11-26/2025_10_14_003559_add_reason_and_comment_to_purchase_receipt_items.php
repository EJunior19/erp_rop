<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('purchase_receipt_items', function (Blueprint $table) {
            $table->string('reason', 30)->nullable()->after('status');
            $table->text('comment')->nullable()->after('reason');
        });

        DB::statement("
            ALTER TABLE purchase_receipt_items
            ADD CONSTRAINT purchase_receipt_items_reason_check
            CHECK (
              reason IS NULL OR reason IN (
                'faltante_proveedor',
                'daño_transporte',
                'backorder',
                'error_pick',
                'otro'
              )
            )
        ");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE purchase_receipt_items DROP CONSTRAINT IF EXISTS purchase_receipt_items_reason_check');
        Schema::table('purchase_receipt_items', function (Blueprint $table) {
            $table->dropColumn(['reason','comment']);
        });
    }
};
