<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        -- 1) Tabla de contadores por categoría (evita choques en concurrencia)
        CREATE TABLE IF NOT EXISTS product_code_counters (
          category_id BIGINT PRIMARY KEY REFERENCES categories(id) ON DELETE CASCADE,
          last_number INTEGER NOT NULL DEFAULT 0
        );

        -- 2) Función trigger: si code viene NULL o vacío, lo genera
        CREATE OR REPLACE FUNCTION fn_products_set_code()
        RETURNS trigger
        LANGUAGE plpgsql
        AS $$
        DECLARE
          v_prefix text;
          v_next   int;
        BEGIN
          -- Si el usuario cargó un código manual, normalizamos y no tocamos más
          IF NEW.code IS NOT NULL AND btrim(NEW.code) <> '' THEN
            NEW.code := upper(btrim(NEW.code));
            RETURN NEW;
          END IF;

          -- Prefijo desde categories.code (debe existir y ser único)
          SELECT upper(btrim(c.code))
            INTO v_prefix
          FROM categories c
          WHERE c.id = NEW.category_id;

          IF v_prefix IS NULL OR v_prefix = '' THEN
            v_prefix := 'PRD';
          END IF;

          -- Garantizar fila en contador
          INSERT INTO product_code_counters(category_id, last_number)
          VALUES (NEW.category_id, 0)
          ON CONFLICT (category_id) DO NOTHING;

          -- Incremento atómico
          UPDATE product_code_counters
             SET last_number = last_number + 1
           WHERE category_id = NEW.category_id
           RETURNING last_number INTO v_next;

          -- Formato: PREFIJO-000001 (6 dígitos)
          NEW.code := v_prefix || '-' || lpad(v_next::text, 6, '0');
          RETURN NEW;
        END;
        $$;

        -- 3) Trigger BEFORE INSERT (porque depende de category_id)
        DROP TRIGGER IF EXISTS trg_products_set_code ON products;

        CREATE TRIGGER trg_products_set_code
        BEFORE INSERT ON products
        FOR EACH ROW
        EXECUTE FUNCTION fn_products_set_code();
        SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
        DROP TRIGGER IF EXISTS trg_products_set_code ON products;
        DROP FUNCTION IF EXISTS fn_products_set_code();
        DROP TABLE IF EXISTS product_code_counters;
        SQL);
    }
};
