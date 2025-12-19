<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('telegram_logs', function (Blueprint $t) {
      $t->id();
      $t->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
      $t->string('direction', 10)->default('out'); // out|in
      $t->string('type', 50)->nullable();          // reminder|overdue|manual|start|saldo|...
      $t->text('message')->nullable();
      $t->string('status', 10)->default('ok');     // ok|error
      $t->json('meta')->nullable();                // {chat_id, api_res, error, update_id, etc}
      $t->timestamps();

      $t->index(['created_at','status']);
      $t->index(['client_id','type']);
    });
  }
  public function down(): void { Schema::dropIfExists('telegram_logs'); }
};


