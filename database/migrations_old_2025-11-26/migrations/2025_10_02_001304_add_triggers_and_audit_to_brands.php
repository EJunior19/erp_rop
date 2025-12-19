<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        /* -------------------------------------------------------
         * 1) Asegurar esquema: brand_logs y columna code en brands
         * ------------------------------------------------------- */
        if (!Schema::hasTable('brand_logs')) {
            Schema::create('brand_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('brand_id')->constrained('brands')->onDelete('cascade');
                $table->string('accion', 20); // insert, update, delete
                $table->json('old_data')->nullable();
                $table->json('new_data')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }

        // code en brands (único). Si ya existe no lo vuelve a crear.
        Schema::table('brands', function (Blueprint $table) {
            if (!Schema::hasColumn('brands', 'code')) {
                $table->string('code', 20)->nullable()->unique()->after('id');
            }
            if (!Schema::hasColumn('brands', 'active')) {
                $table->boolean('active')->default(true)->after('name');
            }
        });

        // Índice único case-insensitive sobre name (si no existe).
        // Si ya tenías un unique normal, dejá este en reemplazo para evitar 'Telefunken' vs 'telefunken'.
        DB::unprepared(<<<'SQL'
        DO $$
        BEGIN
          IF NOT EXISTS (
              SELECT 1
              FROM pg_indexes
              WHERE schemaname = 'public' AND indexname = 'brands_name_lower_unique'
          ) THEN
              CREATE UNIQUE INDEX brands_name_lower_unique
              ON brands (lower(name));
          END IF;
        END $$;
        SQL);

        /* -------------------------------------------------------
         * 2) Limpieza previa de triggers/funciones antiguas
         * ------------------------------------------------------- */
        DB::unprepared(<<<'SQL'
        -- Triggers
        DROP TRIGGER IF EXISTS trg_brands_bi_validate ON brands;
        DROP TRIGGER IF EXISTS trg_brands_ai_code_and_log ON brands;
        DROP TRIGGER IF EXISTS trg_brands_bu_validate ON brands;
        DROP TRIGGER IF EXISTS trg_brands_au_log ON brands;
        DROP TRIGGER IF EXISTS trg_brands_bd_guard ON brands;
        DROP TRIGGER IF EXISTS trg_brands_ad_log ON brands;

        -- Funciones
        DROP FUNCTION IF EXISTS fn_brands_bi_validate();
        DROP FUNCTION IF EXISTS fn_brands_ai_code_and_log();
        DROP FUNCTION IF EXISTS fn_brands_bu_validate();
        DROP FUNCTION IF EXISTS fn_brands_au_log();
        DROP FUNCTION IF EXISTS fn_brands_bd_guard();
        DROP FUNCTION IF EXISTS fn_brands_ad_log();
        SQL);

        /* -------------------------------------------------------
         * 3) Funciones y triggers
         * ------------------------------------------------------- */

        // BEFORE INSERT: normaliza y valida name; rellena active si viene nulo
        DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION fn_brands_bi_validate()
        RETURNS trigger
        LANGUAGE plpgsql AS $$
        DECLARE
            v_name text;
        BEGIN
            -- Normalizar espacios y trim
            v_name := regexp_replace(coalesce(NEW.name, ''), '\s+', ' ', 'g');
            v_name := btrim(v_name);
            NEW.name := v_name;

            IF NEW.name IS NULL OR NEW.name = '' THEN
                RAISE EXCEPTION 'El nombre de la marca es obligatorio';
            END IF;

            -- Unicidad por lower(name), opcionalmente ignorando soft-deletes
            IF EXISTS (
                SELECT 1 FROM brands b
                WHERE lower(b.name) = lower(NEW.name)
                -- AND b.deleted_at IS NULL   -- <- descomentar si usás SoftDeletes en brands
            ) THEN
                RAISE EXCEPTION 'La marca "%" ya existe', NEW.name;
            END IF;

            -- active por defecto si viene nulo
            IF NEW.active IS NULL THEN
                NEW.active := true;
            END IF;

            RETURN NEW;
        END; $$;
        SQL);

        DB::unprepared(<<<'SQL'
        CREATE TRIGGER trg_brands_bi_validate
        BEFORE INSERT ON brands
        FOR EACH ROW
        EXECUTE FUNCTION fn_brands_bi_validate();
        SQL);

        // AFTER INSERT: genera code si no vino y loguea inserción
        DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION fn_brands_ai_code_and_log()
        RETURNS trigger
        LANGUAGE plpgsql AS $$
        DECLARE
            v_code text;
        BEGIN
            IF NEW.code IS NULL OR NEW.code = '' THEN
                v_code := 'BR-' || lpad(NEW.id::text, 5, '0');
                UPDATE brands SET code = v_code WHERE id = NEW.id;
            END IF;

            INSERT INTO brand_logs (brand_id, accion, new_data)
            VALUES (
                NEW.id,
                'insert',
                json_build_object(
                    'code', NEW.code,
                    'name', NEW.name,
                    'active', NEW.active
                )
            );

            RETURN NULL;
        END; $$;
        SQL);

        DB::unprepared(<<<'SQL'
        CREATE TRIGGER trg_brands_ai_code_and_log
        AFTER INSERT ON brands
        FOR EACH ROW
        EXECUTE FUNCTION fn_brands_ai_code_and_log();
        SQL);

        // BEFORE UPDATE: revalida name si cambia + prohibe cambiar code (inmutable)
        DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION fn_brands_bu_validate()
        RETURNS trigger
        LANGUAGE plpgsql AS $$
        DECLARE
            v_name text;
        BEGIN
            -- No permitir cambiar code una vez asignado
            IF NEW.code IS DISTINCT FROM OLD.code AND OLD.code IS NOT NULL THEN
                RAISE EXCEPTION 'El código de la marca no puede modificarse';
            END IF;

            -- Si cambiaron el nombre, normalizar y validar unicidad
            IF NEW.name IS DISTINCT FROM OLD.name THEN
                v_name := regexp_replace(coalesce(NEW.name, ''), '\s+', ' ', 'g');
                v_name := btrim(v_name);
                NEW.name := v_name;

                IF NEW.name IS NULL OR NEW.name = '' THEN
                    RAISE EXCEPTION 'El nombre de la marca es obligatorio';
                END IF;

                IF EXISTS (
                    SELECT 1 FROM brands b
                    WHERE lower(b.name) = lower(NEW.name)
                      AND b.id <> OLD.id
                    -- AND b.deleted_at IS NULL   -- <- descomentar si usás SoftDeletes
                ) THEN
                    RAISE EXCEPTION 'La marca "%" ya existe', NEW.name;
                END IF;
            END IF;

            RETURN NEW;
        END; $$;
        SQL);

        DB::unprepared(<<<'SQL'
        CREATE TRIGGER trg_brands_bu_validate
        BEFORE UPDATE ON brands
        FOR EACH ROW
        EXECUTE FUNCTION fn_brands_bu_validate();
        SQL);

        // AFTER UPDATE: auditoría old/new
        DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION fn_brands_au_log()
        RETURNS trigger
        LANGUAGE plpgsql AS $$
        BEGIN
            INSERT INTO brand_logs (brand_id, accion, old_data, new_data)
            VALUES (
                OLD.id,
                'update',
                json_build_object(
                    'code', OLD.code,
                    'name', OLD.name,
                    'active', OLD.active
                ),
                json_build_object(
                    'code', NEW.code,
                    'name', NEW.name,
                    'active', NEW.active
                )
            );
            RETURN NULL;
        END; $$;
        SQL);

        DB::unprepared(<<<'SQL'
        CREATE TRIGGER trg_brands_au_log
        AFTER UPDATE ON brands
        FOR EACH ROW
        EXECUTE FUNCTION fn_brands_au_log();
        SQL);

        // BEFORE DELETE: impedir borrar si tiene products asociados
        DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION fn_brands_bd_guard()
        RETURNS trigger
        LANGUAGE plpgsql AS $$
        BEGIN
            IF EXISTS (SELECT 1 FROM products WHERE brand_id = OLD.id) THEN
                RAISE EXCEPTION 'No se puede eliminar la marca "%": tiene productos asociados', OLD.name;
            END IF;

            RETURN OLD;
        END; $$;
        SQL);

        DB::unprepared(<<<'SQL'
        CREATE TRIGGER trg_brands_bd_guard
        BEFORE DELETE ON brands
        FOR EACH ROW
        EXECUTE FUNCTION fn_brands_bd_guard();
        SQL);

        // AFTER DELETE: auditoría de eliminación
        DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION fn_brands_ad_log()
        RETURNS trigger
        LANGUAGE plpgsql AS $$
        BEGIN
            INSERT INTO brand_logs (brand_id, accion, old_data)
            VALUES (
                OLD.id,
                'delete',
                json_build_object(
                    'code', OLD.code,
                    'name', OLD.name,
                    'active', OLD.active
                )
            );
            RETURN NULL;
        END; $$;
        SQL);

        DB::unprepared(<<<'SQL'
        CREATE TRIGGER trg_brands_ad_log
        AFTER DELETE ON brands
        FOR EACH ROW
        EXECUTE FUNCTION fn_brands_ad_log();
        SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
        -- Triggers
        DROP TRIGGER IF EXISTS trg_brands_bi_validate ON brands;
        DROP TRIGGER IF EXISTS trg_brands_ai_code_and_log ON brands;
        DROP TRIGGER IF EXISTS trg_brands_bu_validate ON brands;
        DROP TRIGGER IF EXISTS trg_brands_au_log ON brands;
        DROP TRIGGER IF EXISTS trg_brands_bd_guard ON brands;
        DROP TRIGGER IF EXISTS trg_brands_ad_log ON brands;

        -- Funciones
        DROP FUNCTION IF EXISTS fn_brands_bi_validate();
        DROP FUNCTION IF EXISTS fn_brands_ai_code_and_log();
        DROP FUNCTION IF EXISTS fn_brands_bu_validate();
        DROP FUNCTION IF EXISTS fn_brands_au_log();
        DROP FUNCTION IF EXISTS fn_brands_bd_guard();
        DROP FUNCTION IF EXISTS fn_brands_ad_log();
        SQL);

        // Si querés eliminar los logs al hacer rollback, descomentá esta línea:
        // Schema::dropIfExists('brand_logs');
    }
};
