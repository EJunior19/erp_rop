<?php

// database/migrations/2025_10_20_180000_create_contact_logs_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('contact_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('client_id')->constrained()->cascadeOnDelete();
            $t->string('channel', 32);           // telegram|whatsapp|email|sms
            $t->string('type', 32)->nullable();  // ping|promo|aviso|recordatorio|custom
            $t->string('status', 16)->default('queued'); // queued|sent|fail
            $t->string('to_ref')->nullable();    // chat_id / phone / email destino
            $t->string('template')->nullable();  // slug de plantilla (si aplica)
            $t->string('external_id')->nullable(); // id del proveedor
            $t->text('message');                 // cuerpo enviado
            $t->json('meta')->nullable();        // payload extra, errores, etc.
            $t->timestamp('sent_at')->nullable();
            $t->timestamps();
            $t->index(['client_id','channel','status']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('contact_logs');
    }
};

