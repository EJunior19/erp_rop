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
        Schema::create('payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('credit_id')->constrained()->onDelete('cascade'); // relación con crédito
                $table->decimal('amount', 14, 2); // monto abonado
                $table->date('payment_date'); // fecha del pago
                $table->string('method')->nullable(); // opcional: efectivo, transferencia, etc
                $table->timestamps();
            });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
