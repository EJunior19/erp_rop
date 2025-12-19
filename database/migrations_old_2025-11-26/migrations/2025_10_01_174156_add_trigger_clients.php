<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        /* =========================================================
         * LIMPIEZA PREVIA (triggers/funciones de clientes)
         * ========================================================= */
        DB::unprepared(<<<'SQL'
        -- Eliminar triggers conocidos (nombres antiguos y nuevos)
        DO $$
        BEGIN
          IF EXISTS (
            SELECT 1 FROM pg_trigger t
            JOIN pg_class c ON c.oid = t.tgrelid
            WHERE t.tgname = 'trg_clients_before_insert' AND c.relname = 'clients'
          ) THEN
            EXECUTE 'DROP TRIGGER trg_clients_before_insert ON clients';
          END IF;

          IF EXISTS (
            SELECT 1 FROM pg_trigger t
            JOIN pg_class c ON c.oid = t.tgrelid
            WHERE t.tgname = 'trg_clients_bi_validate' AND c.relname = 'clients'
          ) THEN
            EXECUTE 'DROP TRIGGER trg_clients_bi_validate ON clients';
          END IF;

          IF EXISTS (
            SELECT 1 FROM pg_trigger t
            JOIN pg_class c ON c.oid = t.tgrelid
            WHERE t.tgname = 'trg_clients_bu_validate' AND c.relname = 'clients'
          ) THEN
            EXECUTE 'DROP TRIGGER trg_clients_bu_validate ON clients';
          END IF;

          IF EXISTS (
            SELECT 1 FROM pg_trigger t
            JOIN pg_class c ON c.oid = t.tgrelid
            WHERE t.tgname = 'trg_clients_set_code' AND c.relname = 'clients'
          ) THEN
            EXECUTE 'DROP TRIGGER trg_clients_set_code ON clients';
          END IF;
        END $$;

        -- Borrar funciones si existen (CASCADE por dependencias residuales)
        DROP FUNCTION IF EXISTS fn_clients_bi_validate() CASCADE;
        DROP FUNCTION IF EXISTS fn_clients_bu_validate() CASCADE;
        DROP FUNCTION IF EXISTS fn_clients_set_code() CASCADE;
        SQL);

        /* =========================================================
         * BEFORE INSERT: validación (email, ruc, code) soft-delete aware
         * ========================================================= */
        DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION fn_clients_bi_validate()
        RETURNS trigger
        LANGUAGE plpgsql AS $$
        BEGIN
            -- Email único (ignora soft-deleted)
            IF NEW.email IS NOT NULL AND EXISTS (
                SELECT 1 FROM clients c
                WHERE c.email = NEW.email
                  AND c.deleted_at IS NULL
            ) THEN
                RAISE EXCEPTION 'El email ya está registrado en clientes';
            END IF;

            -- RUC único (ignora soft-deleted)
            IF NEW.ruc IS NOT NULL AND EXISTS (
                SELECT 1 FROM clients c
                WHERE c.ruc = NEW.ruc
                  AND c.deleted_at IS NULL
            ) THEN
                RAISE EXCEPTION 'El RUC ya está registrado en clientes';
            END IF;

            -- CODE único si viene manualmente (ignora soft-deleted)
            IF NEW.code IS NOT NULL AND NEW.code <> '' AND EXISTS (
                SELECT 1 FROM clients c
                WHERE c.code = NEW.code
                  AND c.deleted_at IS NULL
            ) THEN
                RAISE EXCEPTION 'El código ya está registrado en clientes';
            END IF;

            RETURN NEW;
        END;
        $$;

        CREATE TRIGGER trg_clients_bi_validate
        BEFORE INSERT ON clients
        FOR EACH ROW
        EXECUTE FUNCTION fn_clients_bi_validate();
        SQL);

        /* =========================================================
         * BEFORE UPDATE: validación al modificar email/ruc/code
         * ========================================================= */
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

            IF NEW.ruc IS DISTINCT FROM OLD.ruc AND NEW.ruc IS NOT NULL AND EXISTS (
                SELECT 1 FROM clients c
                WHERE c.ruc = NEW.ruc
                  AND c.deleted_at IS NULL
                  AND c.id <> OLD.id
            ) THEN
                RAISE EXCEPTION 'El RUC ya está registrado en clientes';
            END IF;

            IF NEW.code IS DISTINCT FROM OLD.code AND NEW.code IS NOT NULL AND NEW.code <> '' AND EXISTS (
                SELECT 1 FROM clients c
                WHERE c.code = NEW.code
                  AND c.deleted_at IS NULL
                  AND c.id <> OLD.id
            ) THEN
                RAISE EXCEPTION 'El código ya está registrado en clientes';
            END IF;

            RETURN NEW;
        END;
        $$;

        CREATE TRIGGER trg_clients_bu_validate
        BEFORE UPDATE ON clients
        FOR EACH ROW
        EXECUTE FUNCTION fn_clients_bu_validate();
        SQL);

        /* =========================================================
         * AFTER INSERT: generar code si está vacío (usa el ID generado)
         * ========================================================= */
        DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION fn_clients_set_code()
        RETURNS trigger
        LANGUAGE plpgsql AS $$
        BEGIN
            -- Si code no vino, establecerlo con prefijo y padding del id
            IF NEW.code IS NULL OR NEW.code = '' THEN
                UPDATE clients
                   SET code = 'C' || lpad(NEW.id::text, 5, '0')
                 WHERE id = NEW.id;
            END IF;
            RETURN NULL; -- AFTER trigger, no modifica NEW
        END;
        $$;

        CREATE TRIGGER trg_clients_set_code
        AFTER INSERT ON clients
        FOR EACH ROW
        EXECUTE FUNCTION fn_clients_set_code();
        SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
        -- Quitar triggers creados
        DROP TRIGGER IF EXISTS trg_clients_bi_validate ON clients;
        DROP TRIGGER IF EXISTS trg_clients_bu_validate ON clients;
        DROP TRIGGER IF EXISTS trg_clients_set_code ON clients;

        -- Quitar funciones
        DROP FUNCTION IF EXISTS fn_clients_bi_validate() CASCADE;
        DROP FUNCTION IF EXISTS fn_clients_bu_validate() CASCADE;
        DROP FUNCTION IF EXISTS fn_clients_set_code() CASCADE;
        SQL);
    }
};
