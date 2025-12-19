<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('purchase_receipts', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('status');

            $table->foreignId('approved_by')
                ->nullable()
                ->after('received_by')
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('approved_at')->nullable()->after('approved_by');

            $table->index('approved_at', 'purchase_receipts_approved_at_idx');
            $table->index('approved_by', 'purchase_receipts_approved_by_idx');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_receipts', function (Blueprint $table) {
            $table->dropIndex('purchase_receipts_approved_at_idx');
            $table->dropIndex('purchase_receipts_approved_by_idx');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['notes','approved_at']);
        });
    }
};
