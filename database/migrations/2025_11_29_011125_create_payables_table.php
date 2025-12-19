<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payables', function (Blueprint $table) {
            $table->id();

            // Relación con la factura del proveedor (compra)
            $table->foreignId('purchase_invoice_id')
                ->constrained()
                ->onDelete('cascade');

            // Proveedor
            $table->foreignId('supplier_id')
                ->constrained('suppliers')
                ->onDelete('restrict');

            // Monto original de la factura
            $table->decimal('total_amount', 14, 2);

            // Si hubo entrega inicial
            $table->decimal('advance_amount', 14, 2)->default(0);

            // Saldo pendiente
            $table->decimal('pending_amount', 14, 2);

            // Plazo de pago (fecha límite)
            $table->date('due_date')->nullable();

            // Condición: contado / crédito / zafra / especial
            $table->string('payment_term')->default('contado');

            // Estado del pagaré
            $table->enum('status', [
                'pendiente',
                'parcial',
                'pagado',
                'vencido'
            ])->default('pendiente');

            // Auditoría
            $table->foreignId('created_by')
                ->constrained('users');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payables');
    }
};
