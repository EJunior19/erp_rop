<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ===== Limpieza previa: elimina triggers/funciones si existen =====
        DB::unprepared(<<<'SQL'
        -- Purchase items
        DROP TRIGGER IF EXISTS trg_purchase_items_after_insert ON purchase_items;
        DROP TRIGGER IF EXISTS trg_purchase_items_after_delete ON purchase_items;
        DROP FUNCTION IF EXISTS fn_purchase_items_ai();
        DROP FUNCTION IF EXISTS fn_purchase_items_ad();

        -- Sale items
        DROP TRIGGER IF EXISTS trg_sale_items_after_insert ON sale_items;
        DROP TRIGGER IF EXISTS trg_sale_items_after_delete ON sale_items;
        DROP FUNCTION IF EXISTS fn_sale_items_ai();
        DROP FUNCTION IF EXISTS fn_sale_items_ad();
        SQL);

        /* ============================================================
         * PURCHASE_ITEMS: AFTER INSERT  (sumar stock + movimiento)
         * ============================================================ */
        DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION fn_purchase_items_ai()
        RETURNS trigger
        LANGUAGE plpgsql AS $$
        BEGIN
            -- Aumentar stock por product_id
            UPDATE products
               SET stock = stock + COALESCE(NEW.qty, 0)
             WHERE id = NEW.product_id;

            -- Registrar movimiento
            INSERT INTO inventory_movements
                (product_id, type, quantity, reason, user_id, created_at, updated_at)
            VALUES
                (NEW.product_id, 'entrada', COALESCE(NEW.qty, 0),
                 'Compra #' || NEW.purchase_id, NULL, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP);

            RETURN NULL; -- AFTER trigger
        END;
        $$;

        CREATE TRIGGER trg_purchase_items_after_insert
        AFTER INSERT ON purchase_items
        FOR EACH ROW
        EXECUTE FUNCTION fn_purchase_items_ai();
        SQL);

        /* ============================================================
         * PURCHASE_ITEMS: AFTER DELETE  (restar stock + movimiento)
         * ============================================================ */
        DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION fn_purchase_items_ad()
        RETURNS trigger
        LANGUAGE plpgsql AS $$
        BEGIN
            -- Disminuir stock por product_id
            UPDATE products
               SET stock = stock - COALESCE(OLD.qty, 0)
             WHERE id = OLD.product_id;

            -- Registrar movimiento
            INSERT INTO inventory_movements
                (product_id, type, quantity, reason, user_id, created_at, updated_at)
            VALUES
                (OLD.product_id, 'salida', COALESCE(OLD.qty, 0),
                 'Eliminación Compra #' || OLD.purchase_id, NULL, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP);

            RETURN NULL; -- AFTER trigger
        END;
        $$;

        CREATE TRIGGER trg_purchase_items_after_delete
        AFTER DELETE ON purchase_items
        FOR EACH ROW
        EXECUTE FUNCTION fn_purchase_items_ad();
        SQL);

        /* ============================================================
         * SALE_ITEMS: AFTER INSERT  (restar stock + movimiento)
         *  - Tu tabla usa product_code: buscamos el id del producto.
         * ============================================================ */
        DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION fn_sale_items_ai()
        RETURNS trigger
        LANGUAGE plpgsql AS $$
        DECLARE
            v_product_id BIGINT;
        BEGIN
            SELECT id INTO v_product_id
              FROM products
             WHERE code = NEW.product_code
             LIMIT 1;

            IF v_product_id IS NULL THEN
                -- Si no hay match, no hacemos nada para evitar error de FK inexistente
                RETURN NULL;
            END IF;

            -- Disminuir stock usando el id hallado
            UPDATE products
               SET stock = stock - COALESCE(NEW.qty, 0)
             WHERE id = v_product_id;

            -- Registrar movimiento
            INSERT INTO inventory_movements
                (product_id, type, quantity, reason, user_id, created_at, updated_at)
            VALUES
                (v_product_id, 'salida', COALESCE(NEW.qty, 0),
                 'Venta #' || NEW.sale_id, NULL, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP);

            RETURN NULL; -- AFTER trigger
        END;
        $$;

        CREATE TRIGGER trg_sale_items_after_insert
        AFTER INSERT ON sale_items
        FOR EACH ROW
        EXECUTE FUNCTION fn_sale_items_ai();
        SQL);

        /* ============================================================
         * SALE_ITEMS: AFTER DELETE  (sumar stock + movimiento)
         * ============================================================ */
        DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION fn_sale_items_ad()
        RETURNS trigger
        LANGUAGE plpgsql AS $$
        DECLARE
            v_product_id BIGINT;
        BEGIN
            SELECT id INTO v_product_id
              FROM products
             WHERE code = OLD.product_code
             LIMIT 1;

            IF v_product_id IS NULL THEN
                RETURN NULL;
            END IF;

            -- Restaurar stock
            UPDATE products
               SET stock = stock + COALESCE(OLD.qty, 0)
             WHERE id = v_product_id;

            -- Registrar movimiento
            INSERT INTO inventory_movements
                (product_id, type, quantity, reason, user_id, created_at, updated_at)
            VALUES
                (v_product_id, 'entrada', COALESCE(OLD.qty, 0),
                 'Eliminación Venta #' || OLD.sale_id, NULL, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP);

            RETURN NULL; -- AFTER trigger
        END;
        $$;

        CREATE TRIGGER trg_sale_items_after_delete
        AFTER DELETE ON sale_items
        FOR EACH ROW
        EXECUTE FUNCTION fn_sale_items_ad();
        SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
        -- Primero triggers
        DROP TRIGGER IF EXISTS trg_purchase_items_after_insert ON purchase_items;
        DROP TRIGGER IF EXISTS trg_purchase_items_after_delete ON purchase_items;
        DROP TRIGGER IF EXISTS trg_sale_items_after_insert ON sale_items;
        DROP TRIGGER IF EXISTS trg_sale_items_after_delete ON sale_items;

        -- Luego funciones
        DROP FUNCTION IF EXISTS fn_purchase_items_ai();
        DROP FUNCTION IF EXISTS fn_purchase_items_ad();
        DROP FUNCTION IF EXISTS fn_sale_items_ai();
        DROP FUNCTION IF EXISTS fn_sale_items_ad();
        SQL);
    }
};
