<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->onDelete('cascade'); // relación con ventas
            $table->foreignId('client_id')->constrained()->onDelete('cascade'); // cliente
            $table->decimal('amount', 14, 2); // monto total del crédito
            $table->decimal('balance', 14, 2); // saldo pendiente
            $table->date('due_date'); // fecha de vencimiento
            $table->enum('status', ['pendiente','pagado','vencido'])->default('pendiente');
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credits');
    }
};
