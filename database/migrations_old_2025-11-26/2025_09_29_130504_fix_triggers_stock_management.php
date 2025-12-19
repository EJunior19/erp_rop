<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        /* ===========================
         * LIMPIEZA DE TRIGGERS/FUNC.
         * =========================== */
        DB::unprepared(<<<'SQL'
        -- Triggers de items
        DROP TRIGGER IF EXISTS trg_purchase_items_ai ON purchase_items;
        DROP TRIGGER IF EXISTS trg_purchase_items_au ON purchase_items;
        DROP TRIGGER IF EXISTS trg_purchase_items_ad ON purchase_items;

        DROP TRIGGER IF EXISTS trg_sale_items_ai ON sale_items;
        DROP TRIGGER IF EXISTS trg_sale_items_au ON sale_items;
        DROP TRIGGER IF EXISTS trg_sale_items_ad ON sale_items;

        -- Triggers de cabeceras (estado/borrado)
        DROP TRIGGER IF EXISTS trg_purchases_au_estado ON purchases;
        DROP TRIGGER IF EXISTS trg_sales_au_estado ON sales;

        DROP TRIGGER IF EXISTS trg_purchases_bd_adjust ON purchases;
        DROP TRIGGER IF EXISTS trg_sales_bd_adjust ON sales;

        -- Funciones
        DROP FUNCTION IF EXISTS fn_purchase_items_ai_stock();
        DROP FUNCTION IF EXISTS fn_purchase_items_au_stock();
        DROP FUNCTION IF EXISTS fn_purchase_items_ad_stock();

        DROP FUNCTION IF EXISTS fn_sale_items_ai_stock();
        DROP FUNCTION IF EXISTS fn_sale_items_au_stock();
        DROP FUNCTION IF EXISTS fn_sale_items_ad_stock();

        DROP FUNCTION IF EXISTS fn_purchases_au_estado_recalc();
        DROP FUNCTION IF EXISTS fn_sales_au_estado_recalc();

        DROP FUNCTION IF EXISTS fn_purchases_bd_adjust();
        DROP FUNCTION IF EXISTS fn_sales_bd_adjust();
        SQL);

        /* ===========================
         * PURCHASE_ITEMS
         * =========================== */

        //-- AFTER INSERT
        DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION fn_purchase_items_ai_stock()
        RETURNS trigger
        LANGUAGE plpgsql AS $$
        BEGIN
            IF EXISTS (
                SELECT 1 FROM purchases p
                 WHERE p.id = NEW.purchase_id
                   AND p.estado = 'aprobado'
            ) THEN
                UPDATE products
                   SET stock = stock + COALESCE(NEW.qty,0)
                 WHERE id = NEW.product_id;
            END IF;
            RETURN NULL;
        END; $$;

        CREATE TRIGGER trg_purchase_items_ai
        AFTER INSERT ON purchase_items
        FOR EACH ROW
        EXECUTE FUNCTION fn_purchase_items_ai_stock();
        SQL);

       // -- AFTER UPDATE (qty y/o product_id)
        DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION fn_purchase_items_au_stock()
        RETURNS trigger
        LANGUAGE plpgsql AS $$
        DECLARE
            delta integer;
        BEGIN
            IF EXISTS (
                SELECT 1 FROM purchases p
                 WHERE p.id = NEW.purchase_id
                   AND p.estado = 'aprobado'
            ) THEN
                IF NEW.product_id IS DISTINCT FROM OLD.product_id THEN
                    UPDATE products SET stock = stock - COALESCE(OLD.qty,0)
                     WHERE id = OLD.product_id;
                    UPDATE products SET stock = stock + COALESCE(NEW.qty,0)
                     WHERE id = NEW.product_id;
                ELSE
                    delta := COALESCE(NEW.qty,0) - COALESCE(OLD.qty,0);
                    IF delta <> 0 THEN
                        UPDATE products SET stock = stock + delta
                         WHERE id = NEW.product_id;
                    END IF;
                END IF;
            END IF;
            RETURN NULL;
        END; $$;

        CREATE TRIGGER trg_purchase_items_au
        AFTER UPDATE ON purchase_items
        FOR EACH ROW
        EXECUTE FUNCTION fn_purchase_items_au_stock();
        SQL);

        //-- AFTER DELETE
        DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION fn_purchase_items_ad_stock()
        RETURNS trigger
        LANGUAGE plpgsql AS $$
        BEGIN
            -- Solo ajusta si la cabecera existe y está aprobada
            IF EXISTS (
                SELECT 1 FROM purchases p
                 WHERE p.id = OLD.purchase_id
                   AND p.estado = 'aprobado'
            ) THEN
                UPDATE products
                   SET stock = stock - COALESCE(OLD.qty,0)
                 WHERE id = OLD.product_id;
            END IF;
            RETURN NULL;
        END; $$;

        CREATE TRIGGER trg_purchase_items_ad
        AFTER DELETE ON purchase_items
        FOR EACH ROW
        EXECUTE FUNCTION fn_purchase_items_ad_stock();
        SQL);

        /* ===========================
         * SALE_ITEMS
         * =========================== */

       // -- AFTER INSERT
        DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION fn_sale_items_ai_stock()
        RETURNS trigger
        LANGUAGE plpgsql AS $$
        BEGIN
            IF EXISTS (
                SELECT 1 FROM sales s
                 WHERE s.id = NEW.sale_id
                   AND s.estado = 'aprobado'
            ) THEN
                UPDATE products
                   SET stock = stock - COALESCE(NEW.qty,0)
                 WHERE id = NEW.product_id;
            END IF;
            RETURN NULL;
        END; $$;

        CREATE TRIGGER trg_sale_items_ai
        AFTER INSERT ON sale_items
        FOR EACH ROW
        EXECUTE FUNCTION fn_sale_items_ai_stock();
        SQL);

        //-- AFTER UPDATE (qty y/o product_id)
        DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION fn_sale_items_au_stock()
        RETURNS trigger
        LANGUAGE plpgsql AS $$
        DECLARE
            delta integer;
        BEGIN
            IF EXISTS (
                SELECT 1 FROM sales s
                 WHERE s.id = NEW.sale_id
                   AND s.estado = 'aprobado'
            ) THEN
                IF NEW.product_id IS DISTINCT FROM OLD.product_id THEN
                    UPDATE products SET stock = stock + COALESCE(OLD.qty,0)
                     WHERE id = OLD.product_id;
                    UPDATE products SET stock = stock - COALESCE(NEW.qty,0)
                     WHERE id = NEW.product_id;
                ELSE
                    delta := COALESCE(NEW.qty,0) - COALESCE(OLD.qty,0);
                    IF delta <> 0 THEN
                        UPDATE products SET stock = stock - delta
                         WHERE id = NEW.product_id;
                    END IF;
                END IF;
            END IF;
            RETURN NULL;
        END; $$;

        CREATE TRIGGER trg_sale_items_au
        AFTER UPDATE ON sale_items
        FOR EACH ROW
        EXECUTE FUNCTION fn_sale_items_au_stock();
        SQL);

        //-- AFTER DELETE
        DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION fn_sale_items_ad_stock()
        RETURNS trigger
        LANGUAGE plpgsql AS $$
        BEGIN
            -- Solo ajusta si la cabecera existe y está aprobada
            IF EXISTS (
                SELECT 1 FROM sales s
                 WHERE s.id = OLD.sale_id
                   AND s.estado = 'aprobado'
            ) THEN
                UPDATE products
                   SET stock = stock + COALESCE(OLD.qty,0)
                 WHERE id = OLD.product_id;
            END IF;
            RETURN NULL;
        END; $$;

        CREATE TRIGGER trg_sale_items_ad
        AFTER DELETE ON sale_items
        FOR EACH ROW
        EXECUTE FUNCTION fn_sale_items_ad_stock();
        SQL);

        /* ======================================
         * CAMBIO DE ESTADO EN CABECERAS (BULK)
         * ====================================== */

        //-- purchases: AFTER UPDATE estado
        DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION fn_purchases_au_estado_recalc()
        RETURNS trigger
        LANGUAGE plpgsql AS $$
        BEGIN
            IF OLD.estado IS DISTINCT FROM NEW.estado THEN
                IF NEW.estado = 'aprobado' THEN
                    UPDATE products p
                       SET stock = p.stock + i.qty
                      FROM purchase_items i
                     WHERE i.product_id = p.id
                       AND i.purchase_id = NEW.id;
                ELSIF OLD.estado = 'aprobado' AND NEW.estado <> 'aprobado' THEN
                    UPDATE products p
                       SET stock = p.stock - i.qty
                      FROM purchase_items i
                     WHERE i.product_id = p.id
                       AND i.purchase_id = NEW.id;
                END IF;
            END IF;
            RETURN NULL;
        END; $$;

        CREATE TRIGGER trg_purchases_au_estado
        AFTER UPDATE ON purchases
        FOR EACH ROW
        EXECUTE FUNCTION fn_purchases_au_estado_recalc();
        SQL);

        //-- sales: AFTER UPDATE estado
        DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION fn_sales_au_estado_recalc()
        RETURNS trigger
        LANGUAGE plpgsql AS $$
        BEGIN
            IF OLD.estado IS DISTINCT FROM NEW.estado THEN
                IF NEW.estado = 'aprobado' THEN
                    UPDATE products p
                       SET stock = p.stock - i.qty
                      FROM sale_items i
                     WHERE i.product_id = p.id
                       AND i.sale_id = NEW.id;
                ELSIF OLD.estado = 'aprobado' AND NEW.estado <> 'aprobado' THEN
                    UPDATE products p
                       SET stock = p.stock + i.qty
                      FROM sale_items i
                     WHERE i.product_id = p.id
                       AND i.sale_id = NEW.id;
                END IF;
            END IF;
            RETURN NULL;
        END; $$;

        CREATE TRIGGER trg_sales_au_estado
        AFTER UPDATE ON sales
        FOR EACH ROW
        EXECUTE FUNCTION fn_sales_au_estado_recalc();
        SQL);

        /* ======================================
         * BORRADO DE CABECERAS (ajuste único)
         * ====================================== */

        //-- purchases: BEFORE DELETE (ajusta y evita doble con triggers de items)
        DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION fn_purchases_bd_adjust()
        RETURNS trigger
        LANGUAGE plpgsql AS $$
        BEGIN
            IF OLD.estado = 'aprobado' THEN
                UPDATE products p
                   SET stock = p.stock - i.qty
                  FROM purchase_items i
                 WHERE i.product_id = p.id
                   AND i.purchase_id = OLD.id;
            END IF;
            RETURN OLD;
        END; $$;

        CREATE TRIGGER trg_purchases_bd_adjust
        BEFORE DELETE ON purchases
        FOR EACH ROW
        EXECUTE FUNCTION fn_purchases_bd_adjust();
        SQL);

       // -- sales: BEFORE DELETE
        DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION fn_sales_bd_adjust()
        RETURNS trigger
        LANGUAGE plpgsql AS $$
        BEGIN
            IF OLD.estado = 'aprobado' THEN
                UPDATE products p
                   SET stock = p.stock + i.qty
                  FROM sale_items i
                 WHERE i.product_id = p.id
                   AND i.sale_id = OLD.id;
            END IF;
            RETURN OLD;
        END; $$;

        CREATE TRIGGER trg_sales_bd_adjust
        BEFORE DELETE ON sales
        FOR EACH ROW
        EXECUTE FUNCTION fn_sales_bd_adjust();
        SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
        -- Triggers
        DROP TRIGGER IF EXISTS trg_purchase_items_ai ON purchase_items;
        DROP TRIGGER IF EXISTS trg_purchase_items_au ON purchase_items;
        DROP TRIGGER IF EXISTS trg_purchase_items_ad ON purchase_items;

        DROP TRIGGER IF EXISTS trg_sale_items_ai ON sale_items;
        DROP TRIGGER IF EXISTS trg_sale_items_au ON sale_items;
        DROP TRIGGER IF EXISTS trg_sale_items_ad ON sale_items;

        DROP TRIGGER IF EXISTS trg_purchases_au_estado ON purchases;
        DROP TRIGGER IF EXISTS trg_sales_au_estado ON sales;

        DROP TRIGGER IF EXISTS trg_purchases_bd_adjust ON purchases;
        DROP TRIGGER IF EXISTS trg_sales_bd_adjust ON sales;

        -- Funciones
        DROP FUNCTION IF EXISTS fn_purchase_items_ai_stock();
        DROP FUNCTION IF EXISTS fn_purchase_items_au_stock();
        DROP FUNCTION IF EXISTS fn_purchase_items_ad_stock();

        DROP FUNCTION IF EXISTS fn_sale_items_ai_stock();
        DROP FUNCTION IF EXISTS fn_sale_items_au_stock();
        DROP FUNCTION IF EXISTS fn_sale_items_ad_stock();

        DROP FUNCTION IF EXISTS fn_purchases_au_estado_recalc();
        DROP FUNCTION IF EXISTS fn_sales_au_estado_recalc();

        DROP FUNCTION IF EXISTS fn_purchases_bd_adjust();
        DROP FUNCTION IF EXISTS fn_sales_bd_adjust();
        SQL);
    }
};
