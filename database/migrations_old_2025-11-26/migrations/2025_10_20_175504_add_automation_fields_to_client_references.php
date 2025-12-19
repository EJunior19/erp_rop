<?php

// database/migrations/2025_10_20_180000_add_automation_fields_to_client_references.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('client_references', function (Blueprint $t) {
            if (!Schema::hasColumn('client_references','telegram')) {
                $t->string('telegram', 64)->nullable()->after('email'); // @usuario
            }
            if (!Schema::hasColumn('client_references','telegram_chat_id')) {
                $t->string('telegram_chat_id', 32)->nullable()->after('telegram');
            }
            if (!Schema::hasColumn('client_references','telegram_link_token')) {
                $t->string('telegram_link_token', 64)->nullable()->after('telegram_chat_id');
            }
            if (!Schema::hasColumn('client_references','notify_opt_in')) {
                $t->boolean('notify_opt_in')->default(true)->after('note');
            }
            if (!Schema::hasColumn('client_references','notify_channels')) {
                $t->json('notify_channels')->nullable()->after('notify_opt_in'); // ej: ["telegram","whatsapp","email"]
            }
        });
    }

    public function down(): void {
        Schema::table('client_references', function (Blueprint $t) {
            $t->dropColumn([
                'telegram','telegram_chat_id','telegram_link_token',
                'notify_opt_in','notify_channels'
            ]);
        });
    }
};
