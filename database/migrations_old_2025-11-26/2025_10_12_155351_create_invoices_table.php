<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->onDelete('cascade');
            $table->string('number', 40)->unique(); // Ej: 001-001-0000123
            $table->string('series', 10)->nullable(); // Serie o sucursal
            $table->date('issued_at')->nullable();
            $table->string('status', 20)->default('issued'); // issued | canceled
            $table->integer('subtotal')->default(0);
            $table->integer('tax')->default(0);
            $table->integer('total')->default(0);
            $table->string('branch_code', 10)->nullable(); // opcional: código de sucursal
            $table->string('cash_register', 10)->nullable();
            $table->string('tax_stamp', 20)->nullable();
            $table->date('tax_stamp_valid_until')->nullable();
            $table->timestamps();

            $table->index(['sale_id', 'issued_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
