<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('purchase_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->string('receipt_number');
            $table->date('received_date');
            $table->foreignId('received_by')->constrained('users');
            $table->enum('status', ['borrador','pendiente_aprobacion','aprobado','rechazado'])->default('borrador');
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('purchase_receipts');
    }
};

