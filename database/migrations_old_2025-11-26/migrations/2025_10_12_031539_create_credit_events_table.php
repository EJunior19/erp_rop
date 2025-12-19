<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('credit_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('credit_id')->constrained('credits')->cascadeOnDelete();
            $t->string('type', 50); // notified, error, overdue_marked, reminder_3d, paid, etc
            $t->json('meta')->nullable();
            $t->timestamps();
            $t->index(['type','created_at']);
        });
    }
    public function down(): void { Schema::dropIfExists('credit_events'); }
};
