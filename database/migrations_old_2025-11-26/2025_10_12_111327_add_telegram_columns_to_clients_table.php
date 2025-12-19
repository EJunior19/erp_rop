<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->unsignedBigInteger('telegram_chat_id')->nullable()->after('phone');
            $table->string('telegram_link_token', 64)->nullable()->after('telegram_chat_id');
            $table->timestamp('telegram_linked_at')->nullable()->after('telegram_link_token');

            // Unique con nombres explícitos para evitar errores en Postgres
            $table->unique('telegram_chat_id', 'clients_telegram_chat_id_unique');
            $table->unique('telegram_link_token', 'clients_telegram_link_token_unique');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropUnique('clients_telegram_chat_id_unique');
            $table->dropUnique('clients_telegram_link_token_unique');
            $table->dropColumn(['telegram_chat_id', 'telegram_link_token', 'telegram_linked_at']);
        });
    }
};
