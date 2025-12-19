<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payable_payments', function (Blueprint $table) {
            $table->id();

            // Relación con la cuenta por pagar
            $table->foreignId('payable_id')
                ->constrained('payables')
                ->onDelete('cascade');

            // Monto pagado
            $table->decimal('amount', 14, 2);

            // Fecha del pago
            $table->date('payment_date');

            // Forma de pago: transferencia, efectivo, cheque, depósito, etc.
            $table->string('method', 50)->default('transferencia');

            // Detalles adicionales
            $table->string('reference', 100)->nullable(); // nro de transferencia, recibo, etc.
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->constrained('users');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payable_payments');
    }
};
