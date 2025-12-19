<?php

// database/migrations/2025_10_13_000001_create_purchase_invoices_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('purchase_invoices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('purchase_receipt_id')->constrained()->cascadeOnDelete();
            $t->string('invoice_number', 50);     // timbrado+numero o como uses
            $t->date('invoice_date');
            $t->decimal('subtotal', 12, 2)->default(0);
            $t->decimal('tax', 12, 2)->default(0);
            $t->decimal('total', 12, 2)->default(0);
            $t->enum('status', ['borrador','emitida','anulada'])->default('borrador');
            $t->text('notes')->nullable();
            $t->foreignId('created_by')->constrained('users');
            $t->timestamps();

            $t->unique(['purchase_receipt_id','invoice_number']); // evita duplicados para la misma recepción
        });
    }
    public function down(): void {
        Schema::dropIfExists('purchase_invoices');
    }
};

