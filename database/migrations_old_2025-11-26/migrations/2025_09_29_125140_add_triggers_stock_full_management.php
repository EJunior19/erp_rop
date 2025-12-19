<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ================
        // PURCHASE_ITEMS
        // ================

        // AFTER INSERT
        DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION fn_purchase_items_ai_stock()
        RETURNS trigger
        LANGUAGE plpgsql
        AS $$
        BEGIN
            IF EXISTS (
                SELECT 1 FROM purchases p
                WHERE p.id = NEW.purchase_id AND p.estado = 'aprobado'
            ) THEN
                UPDATE products
                   SET stock = stock + COALESCE(NEW.qty,0)
                 WHERE id = NEW.product_id;
            END IF;

            RETURN NULL; -- AFTER trigger
        END;
        $$;

        DROP TRIGGER IF EXISTS trg_purchase_items_ai ON purchase_items;
        CREATE TRIGGER trg_purchase_items_ai
        AFTER INSERT ON purchase_items
        FOR EACH ROW
        EXECUTE FUNCTION fn_purchase_items_ai_stock();
        SQL);

        // AFTER UPDATE (maneja cambio de qty y/o product_id)
        DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION fn_purchase_items_au_stock()
        RETURNS trigger
        LANGUAGE plpgsql
        AS $$
        DECLARE
            delta integer;
        BEGIN
            IF EXISTS (
                SELECT 1 FROM purchases p
                WHERE p.id = NEW.purchase_id AND p.estado = 'aprobado'
            ) THEN
                -- Si cambió el product_id, devolver al viejo y sumar al nuevo
                IF NEW.product_id IS DISTINCT FROM OLD.product_id THEN
                    -- devolver stock al producto viejo
                    UPDATE products
                       SET stock = stock - COALESCE(OLD.qty,0)
                     WHERE id = OLD.product_id;

                    -- sumar stock al producto nuevo
                    UPDATE products
                       SET stock = stock + COALESCE(NEW.qty,0)
                     WHERE id = NEW.product_id;

                ELSE
                    -- mismo producto: ajustar por diferencia de qty
                    delta := COALESCE(NEW.qty,0) - COALESCE(OLD.qty,0);
                    IF delta <> 0 THEN
                        UPDATE products
                           SET stock = stock + delta
                         WHERE id = NEW.product_id;
                    END IF;
                END IF;
            END IF;

            RETURN NULL;
        END;
        $$;

        DROP TRIGGER IF EXISTS trg_purchase_items_au ON purchase_items;
        CREATE TRIGGER trg_purchase_items_au
        AFTER UPDATE ON purchase_items
        FOR EACH ROW
        EXECUTE FUNCTION fn_purchase_items_au_stock();
        SQL);

        // AFTER DELETE
        DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION fn_purchase_items_ad_stock()
        RETURNS trigger
        LANGUAGE plpgsql
        AS $$
        BEGIN
            IF EXISTS (
                SELECT 1 FROM purchases p
                WHERE p.id = OLD.purchase_id AND p.estado = 'aprobado'
            ) THEN
                UPDATE products
                   SET stock = stock - COALESCE(OLD.qty,0)
                 WHERE id = OLD.product_id;
            END IF;

            RETURN NULL;
        END;
        $$;

        DROP TRIGGER IF EXISTS trg_purchase_items_ad ON purchase_items;
        CREATE TRIGGER trg_purchase_items_ad
        AFTER DELETE ON purchase_items
        FOR EACH ROW
        EXECUTE FUNCTION fn_purchase_items_ad_stock();
        SQL);

        // =================
        //   SALE_ITEMS
        // =================

        // AFTER INSERT
        DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION fn_sale_items_ai_stock()
        RETURNS trigger
        LANGUAGE plpgsql
        AS $$
        BEGIN
            IF EXISTS (
                SELECT 1 FROM sales s
                WHERE s.id = NEW.sale_id AND s.estado = 'aprobado'
            ) THEN
                UPDATE products
                   SET stock = stock - COALESCE(NEW.qty,0)
                 WHERE id = NEW.product_id;
            END IF;

            RETURN NULL;
        END;
        $$;

        DROP TRIGGER IF EXISTS trg_sale_items_ai ON sale_items;
        CREATE TRIGGER trg_sale_items_ai
        AFTER INSERT ON sale_items
        FOR EACH ROW
        EXECUTE FUNCTION fn_sale_items_ai_stock();
        SQL);

        // AFTER UPDATE (maneja cambio de qty y/o product_id)
        DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION fn_sale_items_au_stock()
        RETURNS trigger
        LANGUAGE plpgsql
        AS $$
        DECLARE
            delta integer;
        BEGIN
            IF EXISTS (
                SELECT 1 FROM sales s
                WHERE s.id = NEW.sale_id AND s.estado = 'aprobado'
            ) THEN
                IF NEW.product_id IS DISTINCT FROM OLD.product_id THEN
                    -- devolver al producto viejo
                    UPDATE products
                       SET stock = stock + COALESCE(OLD.qty,0)
                     WHERE id = OLD.product_id;

                    -- descontar del nuevo
                    UPDATE products
                       SET stock = stock - COALESCE(NEW.qty,0)
                     WHERE id = NEW.product_id;
                ELSE
                    delta := COALESCE(NEW.qty,0) - COALESCE(OLD.qty,0);
                    IF delta <> 0 THEN
                        UPDATE products
                           SET stock = stock - delta
                         WHERE id = NEW.product_id;
                    END IF;
                END IF;
            END IF;

            RETURN NULL;
        END;
        $$;

        DROP TRIGGER IF EXISTS trg_sale_items_au ON sale_items;
        CREATE TRIGGER trg_sale_items_au
        AFTER UPDATE ON sale_items
        FOR EACH ROW
        EXECUTE FUNCTION fn_sale_items_au_stock();
        SQL);

        // AFTER DELETE
        DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION fn_sale_items_ad_stock()
        RETURNS trigger
        LANGUAGE plpgsql
        AS $$
        BEGIN
            IF EXISTS (
                SELECT 1 FROM sales s
                WHERE s.id = OLD.sale_id AND s.estado = 'aprobado'
            ) THEN
                UPDATE products
                   SET stock = stock + COALESCE(OLD.qty,0)
                 WHERE id = OLD.product_id;
            END IF;

            RETURN NULL;
        END;
        $$;

        DROP TRIGGER IF EXISTS trg_sale_items_ad ON sale_items;
        CREATE TRIGGER trg_sale_items_ad
        AFTER DELETE ON sale_items
        FOR EACH ROW
        EXECUTE FUNCTION fn_sale_items_ad_stock();
        SQL);

        // ============================================
        //  CAMBIO DE ESTADO EN PURCHASES (bloque)
        // ============================================
        DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION fn_purchases_au_estado_recalc()
        RETURNS trigger
        LANGUAGE plpgsql
        AS $$
        BEGIN
            IF OLD.estado IS DISTINCT FROM NEW.estado THEN
                -- Pasa a aprobado: sumar todo lo de sus items
                IF NEW.estado = 'aprobado' THEN
                    UPDATE products p
                       SET stock = p.stock + i.qty
                      FROM purchase_items i
                     WHERE i.product_id = p.id
                       AND i.purchase_id = NEW.id;

                -- Sale de aprobado: restar todo lo que antes se había sumado
                ELSIF OLD.estado = 'aprobado' AND NEW.estado <> 'aprobado' THEN
                    UPDATE products p
                       SET stock = p.stock - i.qty
                      FROM purchase_items i
                     WHERE i.product_id = p.id
                       AND i.purchase_id = NEW.id;
                END IF;
            END IF;

            RETURN NULL;
        END;
        $$;

        DROP TRIGGER IF EXISTS trg_purchases_au_estado ON purchases;
        CREATE TRIGGER trg_purchases_au_estado
        AFTER UPDATE ON purchases
        FOR EACH ROW
        EXECUTE FUNCTION fn_purchases_au_estado_recalc();
        SQL);

        // ======================================
        //  CAMBIO DE ESTADO EN SALES (bloque)
        // ======================================
        DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION fn_sales_au_estado_recalc()
        RETURNS trigger
        LANGUAGE plpgsql
        AS $$
        BEGIN
            IF OLD.estado IS DISTINCT FROM NEW.estado THEN
                -- Pasa a aprobado: descontar todo
                IF NEW.estado = 'aprobado' THEN
                    UPDATE products p
                       SET stock = p.stock - i.qty
                      FROM sale_items i
                     WHERE i.product_id = p.id
                       AND i.sale_id = NEW.id;

                -- Sale de aprobado: devolver stock
                ELSIF OLD.estado = 'aprobado' AND NEW.estado <> 'aprobado' THEN
                    UPDATE products p
                       SET stock = p.stock + i.qty
                      FROM sale_items i
                     WHERE i.product_id = p.id
                       AND i.sale_id = NEW.id;
                END IF;
            END IF;

            RETURN NULL;
        END;
        $$;

        DROP TRIGGER IF EXISTS trg_sales_au_estado ON sales;
        CREATE TRIGGER trg_sales_au_estado
        AFTER UPDATE ON sales
        FOR EACH ROW
        EXECUTE FUNCTION fn_sales_au_estado_recalc();
        SQL);
    }

    public function down(): void
    {
        // Triggers primero
        DB::unprepared(<<<'SQL'
        DROP TRIGGER IF EXISTS trg_purchase_items_ai ON purchase_items;
        DROP TRIGGER IF EXISTS trg_purchase_items_au ON purchase_items;
        DROP TRIGGER IF EXISTS trg_purchase_items_ad ON purchase_items;

        DROP TRIGGER IF EXISTS trg_sale_items_ai ON sale_items;
        DROP TRIGGER IF EXISTS trg_sale_items_au ON sale_items;
        DROP TRIGGER IF EXISTS trg_sale_items_ad ON sale_items;

        DROP TRIGGER IF EXISTS trg_purchases_au_estado ON purchases;
        DROP TRIGGER IF EXISTS trg_sales_au_estado ON sales;
        SQL);

        // Funciones después
        DB::unprepared(<<<'SQL'
        DROP FUNCTION IF EXISTS fn_purchase_items_ai_stock();
        DROP FUNCTION IF EXISTS fn_purchase_items_au_stock();
        DROP FUNCTION IF EXISTS fn_purchase_items_ad_stock();

        DROP FUNCTION IF EXISTS fn_sale_items_ai_stock();
        DROP FUNCTION IF EXISTS fn_sale_items_au_stock();
        DROP FUNCTION IF EXISTS fn_sale_items_ad_stock();

        DROP FUNCTION IF EXISTS fn_purchases_au_estado_recalc();
        DROP FUNCTION IF EXISTS fn_sales_au_estado_recalc();
        SQL);
    }
};
