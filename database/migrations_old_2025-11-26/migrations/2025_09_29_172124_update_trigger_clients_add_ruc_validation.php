<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // --- Limpieza robusta: elimina cualquier trigger previo que apunte a la función
        DB::unprepared(<<<'SQL'
        -- Intenta borrar ambos nombres de trigger (según versiones previas)
        DO $$
        BEGIN
          -- trg_clients_before_insert (nombre antiguo estilo MySQL)
          IF EXISTS (
            SELECT 1
            FROM pg_trigger t
            JOIN pg_class c ON c.oid = t.tgrelid
            WHERE t.tgname = 'trg_clients_before_insert' AND c.relname = 'clients'
          ) THEN
            EXECUTE 'DROP TRIGGER trg_clients_before_insert ON clients';
          END IF;

          -- trg_clients_bi_validate (nombre nuevo)
          IF EXISTS (
            SELECT 1
            FROM pg_trigger t
            JOIN pg_class c ON c.oid = t.tgrelid
            WHERE t.tgname = 'trg_clients_bi_validate' AND c.relname = 'clients'
          ) THEN
            EXECUTE 'DROP TRIGGER trg_clients_bi_validate ON clients';
          END IF;
        END $$;

        -- Borra la función; CASCADE por si quedó algún dependiente residual
        DROP FUNCTION IF EXISTS fn_clients_bi_validate() CASCADE;
        SQL);

        // --- Crea función BEFORE INSERT con validación de email, ruc y code (soft delete-aware)
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

            -- Código único (si viene seteado manualmente)
            IF NEW.code IS NOT NULL AND EXISTS (
                SELECT 1 FROM clients c
                WHERE c.code = NEW.code
                  AND c.deleted_at IS NULL
            ) THEN
                RAISE EXCEPTION 'El código ya está registrado en clientes';
            END IF;

            RETURN NEW;
        END;
        $$;
        SQL);

        // --- Crea el trigger con un nombre claro y consistente
        DB::unprepared(<<<'SQL'
        CREATE TRIGGER trg_clients_bi_validate
        BEFORE INSERT ON clients
        FOR EACH ROW
        EXECUTE FUNCTION fn_clients_bi_validate();
        SQL);
    }

    public function down(): void
    {
        // Limpieza simétrica
        DB::unprepared(<<<'SQL'
        DO $$
        BEGIN
          IF EXISTS (
            SELECT 1
            FROM pg_trigger t
            JOIN pg_class c ON c.oid = t.tgrelid
            WHERE t.tgname = 'trg_clients_bi_validate' AND c.relname = 'clients'
          ) THEN
            EXECUTE 'DROP TRIGGER trg_clients_bi_validate ON clients';
          END IF;

          IF EXISTS (
            SELECT 1
            FROM pg_trigger t
            JOIN pg_class c ON c.oid = t.tgrelid
            WHERE t.tgname = 'trg_clients_before_insert' AND c.relname = 'clients'
          ) THEN
            EXECUTE 'DROP TRIGGER trg_clients_before_insert ON clients';
          END IF;
        END $$;

        DROP FUNCTION IF EXISTS fn_clients_bi_validate() CASCADE;
        SQL);

        // Si querés restaurar la versión sin RUC, podrías volver a crear otra función/trigger aquí.
    }
};
