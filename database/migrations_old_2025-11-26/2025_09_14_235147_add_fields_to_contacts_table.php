<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            if (!Schema::hasColumn('contacts', 'name')) {
                $table->string('name')->after('client_id');
            }
            if (!Schema::hasColumn('contacts', 'email')) {
                $table->string('email')->nullable()->after('name');
            }
            if (!Schema::hasColumn('contacts', 'phone')) {
                $table->string('phone')->nullable()->after('email');
            }
            if (!Schema::hasColumn('contacts', 'position')) {
                $table->string('position')->nullable()->after('phone');
            }
            if (!Schema::hasColumn('contacts', 'notes')) {
                $table->text('notes')->nullable()->after('position');
            }
            if (!Schema::hasColumn('contacts', 'active')) {
                $table->boolean('active')->default(true)->after('notes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            if (Schema::hasColumn('contacts', 'name')) {
                $table->dropColumn('name');
            }
            if (Schema::hasColumn('contacts', 'email')) {
                $table->dropColumn('email');
            }
            if (Schema::hasColumn('contacts', 'phone')) {
                $table->dropColumn('phone');
            }
            if (Schema::hasColumn('contacts', 'position')) {
                $table->dropColumn('position');
            }
            if (Schema::hasColumn('contacts', 'notes')) {
                $table->dropColumn('notes');
            }
            if (Schema::hasColumn('contacts', 'active')) {
                $table->dropColumn('active');
            }
        });
    }
};
