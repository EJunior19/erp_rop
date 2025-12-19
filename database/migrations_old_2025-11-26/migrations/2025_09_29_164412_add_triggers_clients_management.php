<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        /* ===========================================
         * Tabla de auditoría (solo si no existe)
         * =========================================== */
        if (!Schema::hasTable('client_logs')) {
            Schema::create('client_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('client_id')->constrained()->onDelete('cascade');
                $table->string('accion', 20); // insert, update, delete
                $table->json('old_data')->nullable();
                $table->json('new_data')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }

        /* ===========================================
         * Limpieza previa (triggers y funciones)
         * =========================================== */
        DB::unprepared(<<<'SQL'
        -- Triggers
        DROP TRIGGER IF EXISTS trg_clients_bi_validate ON clients;
        DROP TRIGGER IF EXISTS trg_clients_bu_validate ON clients;
        DROP TRIGGER IF EXISTS trg_clients_bd_guard ON clients;
        DROP TRIGGER IF EXISTS trg_clients_ai_log ON clients;
        DROP TRIGGER IF EXISTS trg_clients_au_log ON clients;
        DROP TRIGGER IF EXISTS trg_clients_ad_log ON clients;

        -- Funciones
        DROP FUNCTION IF EXISTS fn_clients_bi_validate();
        DROP FUNCTION IF EXISTS fn_clients_bu_validate();
        DROP FUNCTION IF EXISTS fn_clients_bd_guard();
        DROP FUNCTION IF EXISTS fn_clients_ai_log();
        DROP FUNCTION IF EXISTS fn_clients_au_log();
        DROP FUNCTION IF EXISTS fn_clients_ad_log();
        SQL);

        /* ===========================================
         * Validaciones de unicidad (INSERT / UPDATE)
         * =========================================== */

        // BEFORE INSERT: email y code únicos (considerando soft deletes)
        DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION fn_clients_bi_validate()
        RETURNS trigger
        LANGUAGE plpgsql AS $$
        BEGIN
            IF NEW.email IS NOT NULL AND EXISTS (
                SELECT 1 FROM clients c
                WHERE c.email = NEW.email
                  AND c.deleted_at IS NULL
            ) THEN
                RAISE EXCEPTION 'El email ya está registrado en clientes';
            END IF;

            IF NEW.code IS NOT NULL AND EXISTS (
                SELECT 1 FROM clients c
                WHERE c.code = NEW.code
                  AND c.deleted_at IS NULL
            ) THEN
                RAISE EXCEPTION 'El código ya está registrado en clientes';
            END IF;

            RETURN NEW;
        END; $$;

        CREATE TRIGGER trg_clients_bi_validate
        BEFORE INSERT ON clients
        FOR EACH ROW
        EXECUTE FUNCTION fn_clients_bi_validate();
        SQL);

        // BEFORE UPDATE: validar cuando cambien email/code
        DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION fn_clients_bu_validate()
        RETURNS trigger
        LANGUAGE plpgsql AS $$
        BEGIN
            IF NEW.email IS DISTINCT FROM OLD.email AND NEW.email IS NOT NULL AND EXISTS (
                SELECT 1 FROM clients c
                WHERE c.email = NEW.email
                  AND c.deleted_at IS NULL
                  AND c.id <> OLD.id
            ) THEN
                RAISE EXCEPTION 'El email ya está registrado en clientes';
            END IF;

            IF NEW.code IS DISTINCT FROM OLD.code AND NEW.code IS NOT NULL AND EXISTS (
                SELECT 1 FROM clients c
                WHERE c.code = NEW.code
                  AND c.deleted_at IS NULL
                  AND c.id <> OLD.id
            ) THEN
                RAISE EXCEPTION 'El código ya está registrado en clientes';
            END IF;

            RETURN NEW;
        END; $$;

        CREATE TRIGGER trg_clients_bu_validate
        BEFORE UPDATE ON clients
        FOR EACH ROW
        EXECUTE FUNCTION fn_clients_bu_validate();
        SQL);

        /* ===========================================
         * Guardas de integridad en DELETE
         * =========================================== */
        DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION fn_clients_bd_guard()
        RETURNS trigger
        LANGUAGE plpgsql AS $$
        BEGIN
            IF EXISTS (SELECT 1 FROM sales WHERE client_id = OLD.id) THEN
                RAISE EXCEPTION 'No se puede eliminar un cliente con ventas registradas';
            END IF;

            IF EXISTS (SELECT 1 FROM credits WHERE client_id = OLD.id) THEN
                RAISE EXCEPTION 'No se puede eliminar un cliente con créditos registrados';
            END IF;

            RETURN OLD;
        END; $$;

        CREATE TRIGGER trg_clients_bd_guard
        BEFORE DELETE ON clients
        FOR EACH ROW
        EXECUTE FUNCTION fn_clients_bd_guard();
        SQL);

        /* ===========================================
         * Auditoría (INSERT / UPDATE / DELETE)
         * =========================================== */

        // AFTER INSERT: log de inserción (solo new_data)
        DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION fn_clients_ai_log()
        RETURNS trigger
        LANGUAGE plpgsql AS $$
        BEGIN
            INSERT INTO client_logs (client_id, accion, new_data)
            VALUES (
                NEW.id,
                'insert',
                json_build_object(
                    'name', NEW.name,
                    'email', NEW.email,
                    'phone', NEW.phone,
                    'code', NEW.code
                )
            );
            RETURN NULL;
        END; $$;

        CREATE TRIGGER trg_clients_ai_log
        AFTER INSERT ON clients
        FOR EACH ROW
        EXECUTE FUNCTION fn_clients_ai_log();
        SQL);

        // AFTER UPDATE: log de cambio (old_data y new_data)
        DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION fn_clients_au_log()
        RETURNS trigger
        LANGUAGE plpgsql AS $$
        BEGIN
            INSERT INTO client_logs (client_id, accion, old_data, new_data)
            VALUES (
                OLD.id,
                'update',
                json_build_object(
                    'name', OLD.name,
                    'email', OLD.email,
                    'phone', OLD.phone,
                    'code', OLD.code
                ),
                json_build_object(
                    'name', NEW.name,
                    'email', NEW.email,
                    'phone', NEW.phone,
                    'code', NEW.code
                )
            );
            RETURN NULL;
        END; $$;

        CREATE TRIGGER trg_clients_au_log
        AFTER UPDATE ON clients
        FOR EACH ROW
        EXECUTE FUNCTION fn_clients_au_log();
        SQL);

        // AFTER DELETE: log de eliminación (solo old_data)
        DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION fn_clients_ad_log()
        RETURNS trigger
        LANGUAGE plpgsql AS $$
        BEGIN
            INSERT INTO client_logs (client_id, accion, old_data)
            VALUES (
                OLD.id,
                'delete',
                json_build_object(
                    'name', OLD.name,
                    'email', OLD.email,
                    'phone', OLD.phone,
                    'code', OLD.code
                )
            );
            RETURN NULL;
        END; $$;

        CREATE TRIGGER trg_clients_ad_log
        AFTER DELETE ON clients
        FOR EACH ROW
        EXECUTE FUNCTION fn_clients_ad_log();
        SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
        -- Triggers
        DROP TRIGGER IF EXISTS trg_clients_bi_validate ON clients;
        DROP TRIGGER IF EXISTS trg_clients_bu_validate ON clients;
        DROP TRIGGER IF EXISTS trg_clients_bd_guard ON clients;
        DROP TRIGGER IF EXISTS trg_clients_ai_log ON clients;
        DROP TRIGGER IF EXISTS trg_clients_au_log ON clients;
        DROP TRIGGER IF EXISTS trg_clients_ad_log ON clients;

        -- Funciones
        DROP FUNCTION IF EXISTS fn_clients_bi_validate();
        DROP FUNCTION IF EXISTS fn_clients_bu_validate();
        DROP FUNCTION IF EXISTS fn_clients_bd_guard();
        DROP FUNCTION IF EXISTS fn_clients_ai_log();
        DROP FUNCTION IF EXISTS fn_clients_au_log();
        DROP FUNCTION IF EXISTS fn_clients_ad_log();
        SQL);

        Schema::dropIfExists('client_logs');
    }
};
