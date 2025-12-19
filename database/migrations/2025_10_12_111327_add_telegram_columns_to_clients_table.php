<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
            {
                Schema::table('clients', function (Blueprint $table) {
                    // ⚠️ NO volvemos a crear telegram_chat_id, eso ya lo hace la migración 010554
                    // $table->unsignedBigInteger('telegram_chat_id')->nullable()->after('phone');

                    $table->string('telegram_link_token', 64)->nullable()->after('telegram_chat_id');
                    $table->timestamp('telegram_linked_at')->nullable()->after('telegram_link_token');

                    // El índice UNIQUE sobre telegram_chat_id sí puede quedar, porque la columna ya existe
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
