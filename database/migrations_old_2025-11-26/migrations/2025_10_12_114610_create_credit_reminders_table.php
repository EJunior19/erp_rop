<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('credit_reminders', function (Blueprint $t) {
            $t->id();
            $t->foreignId('credit_id')->constrained()->cascadeOnDelete();
            $t->date('due_date');                 // redundante pero práctico para consultas
            $t->unsignedTinyInteger('days_before'); // ej.: 3, 1, 0
            $t->timestamp('sent_at')->nullable();
            $t->timestamps();

            $t->unique(['credit_id', 'days_before']); // evita duplicados por crédito/offset
            $t->index(['due_date', 'days_before']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_reminders');
    }
};
