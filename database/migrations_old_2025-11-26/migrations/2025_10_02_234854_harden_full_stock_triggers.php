<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        /* ============================================================
         * 0) LIMPIEZA DE LEGADOS / TRIGGERS CONFLICTIVOS
         *    (para evitar doble conteo o lógicas sin "estado")
         * ============================================================ */

        -- Triggers/funciones "inventory_triggers" (sin chequeo de estado)
        DROP TRIGGER IF EXISTS trg_purchase_items_after_insert ON purchase_items;
        DROP TRIGGER IF EXISTS trg_purchase_items_after_delete ON purchase_items;
        DROP TRIGGER IF EXISTS trg_sale_items_after_insert     ON sale_items;
        DROP TRIGGER IF EXISTS trg_sale_items_after_delete     ON sale_items;

        DROP FUNCTION IF EXISTS fn_purchase_items_ai();
        DROP FUNCTION IF EXISTS fn_purchase_items_ad();
        DROP FUNCTION IF EXISTS fn_sale_items_ai();
        DROP FUNCTION IF EXISTS fn_sale_items_ad();

        -- Triggers/funciones previos (por si quedaron versiones antiguas)
        DROP TRIGGER IF EXISTS trg_purchase_items_ai ON purchase_items;
        DROP TRIGGER IF EXISTS trg_purchase_items_au ON purchase_items;
        DROP TRIGGER IF EXISTS trg_purchase_items_ad ON purchase_items;

        DROP TRIGGER IF EXISTS trg_sale_items_ai ON sale_items;
        DROP TRIGGER IF EXISTS trg_sale_items_au ON sale_items;
        DROP TRIGGER IF EXISTS trg_sale_items_ad ON sale_items;

        DROP TRIGGER IF EXISTS trg_purchases_au_estado ON purchases;
        DROP TRIGGER IF EXISTS trg_sales_au_estado     ON sales;

        DROP TRIGGER IF EXISTS trg_purchases_bd_adjust ON purchases;
        DROP TRIGGER IF EXISTS trg_sales_bd_adjust     ON sales;

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

        /* ============================================================
         * 1) SANEOS BASE: STOCK/ÍNDICES/CHECKS
         * ============================================================ */

        -- Asegurar columna stock consistente
        ALTER TABLE products ALTER COLUMN stock SET DEFAULT 0;
        UPDATE products SET stock = 0 WHERE stock IS NULL;
        ALTER TABLE products ALTER COLUMN stock SET NOT NULL;

        -- Índices para que los triggers vuelen
        CREATE INDEX IF NOT EXISTS idx_purchase_items_purchase ON purchase_items(purchase_id);
        CREATE INDEX IF NOT EXISTS idx_purchase_items_product  ON purchase_items(product_id);
        CREATE INDEX IF NOT EXISTS idx_sale_items_sale         ON sale_items(sale_id);
        CREATE INDEX IF NOT EXISTS idx_sale_items_product      ON sale_items(product_id);
        CREATE INDEX IF NOT EXISTS idx_purchases_estado        ON purchases(estado);
        CREATE INDEX IF NOT EXISTS idx_sales_estado            ON sales(estado);

        -- Checks de dominio (solo si no existen)
        DO $$
        BEGIN
          IF NOT EXISTS (
            SELECT 1 FROM information_schema.constraint_column_usage
            WHERE table_name='sales' AND constraint_name='sales_estado_chk'
          ) THEN
            ALTER TABLE sales
              ADD CONSTRAINT sales_estado_chk
              CHECK (estado IN ('pendiente_aprobación','aprobado','rechazado','editable','cancelado'));
          END IF;

          IF NOT EXISTS (
            SELECT 1 FROM information_schema.constraint_column_usage
            WHERE table_name='purchases' AND constraint_name='purchases_estado_chk'
          ) THEN
            ALTER TABLE purchases
              ADD CONSTRAINT purchases_estado_chk
              CHECK (estado IN ('pendiente','aprobado','rechazado','cancelado'));
          END IF;
        END $$;

        /* ============================================================
         * 2) PURCHASE_ITEMS: SOLO CUANDO LA COMPRA ESTÁ APROBADA
         *     (sumas/restas y cambios de qty/producto)
         * ============================================================ */

        CREATE OR REPLACE FUNCTION fn_purchase_items_ai_stock()
        RETURNS trigger LANGUAGE plpgsql AS $$
        BEGIN
          IF EXISTS (SELECT 1 FROM purchases p WHERE p.id = NEW.purchase_id AND p.estado = 'aprobado') THEN
            UPDATE products SET stock = stock + COALESCE(NEW.qty,0) WHERE id = NEW.product_id;
          END IF;
          RETURN NULL;
        END $$;

        CREATE OR REPLACE FUNCTION fn_purchase_items_au_stock()
        RETURNS trigger LANGUAGE plpgsql AS $$
        DECLARE delta integer;
        BEGIN
          IF EXISTS (SELECT 1 FROM purchases p WHERE p.id = NEW.purchase_id AND p.estado = 'aprobado') THEN
            IF NEW.product_id IS DISTINCT FROM OLD.product_id THEN
              UPDATE products SET stock = stock - COALESCE(OLD.qty,0) WHERE id = OLD.product_id;
              UPDATE products SET stock = stock + COALESCE(NEW.qty,0) WHERE id = NEW.product_id;
            ELSE
              delta := COALESCE(NEW.qty,0) - COALESCE(OLD.qty,0);
              IF delta <> 0 THEN
                UPDATE products SET stock = stock + delta WHERE id = NEW.product_id;
              END IF;
            END IF;
          END IF;
          RETURN NULL;
        END $$;

        CREATE OR REPLACE FUNCTION fn_purchase_items_ad_stock()
        RETURNS trigger LANGUAGE plpgsql AS $$
        BEGIN
          IF EXISTS (SELECT 1 FROM purchases p WHERE p.id = OLD.purchase_id AND p.estado = 'aprobado') THEN
            UPDATE products SET stock = stock - COALESCE(OLD.qty,0) WHERE id = OLD.product_id;
          END IF;
          RETURN NULL;
        END $$;

        CREATE TRIGGER trg_purchase_items_ai
        AFTER INSERT ON purchase_items FOR EACH ROW
        EXECUTE FUNCTION fn_purchase_items_ai_stock();

        CREATE TRIGGER trg_purchase_items_au
        AFTER UPDATE ON purchase_items FOR EACH ROW
        EXECUTE FUNCTION fn_purchase_items_au_stock();

        CREATE TRIGGER trg_purchase_items_ad
        AFTER DELETE ON purchase_items FOR EACH ROW
        EXECUTE FUNCTION fn_purchase_items_ad_stock();

        /* ============================================================
         * 3) SALE_ITEMS: ENDURECIDO (NO PERMITE STOCK NEGATIVO)
         *     - Valida con SELECT ... FOR UPDATE
         *     - Lanza EXCEPCIÓN 23514 si no alcanza
         * ============================================================ */

        CREATE OR REPLACE FUNCTION fn_sale_items_ai_stock()
        RETURNS trigger LANGUAGE plpgsql AS $$
        DECLARE _stock integer;
        BEGIN
          IF EXISTS (SELECT 1 FROM sales s WHERE s.id = NEW.sale_id AND s.estado = 'aprobado') THEN
            SELECT stock INTO _stock FROM products WHERE id = NEW.product_id FOR UPDATE;
            IF COALESCE(_stock,0) < COALESCE(NEW.qty,0) THEN
              RAISE EXCEPTION 'Stock insuficiente (prod %, stock %, req %)', NEW.product_id, _stock, NEW.qty
                USING ERRCODE = '23514';
            END IF;
            UPDATE products SET stock = stock - COALESCE(NEW.qty,0) WHERE id = NEW.product_id;
          END IF;
          RETURN NULL;
        END $$;

        CREATE OR REPLACE FUNCTION fn_sale_items_au_stock()
        RETURNS trigger LANGUAGE plpgsql AS $$
        DECLARE delta integer; _stock integer;
        BEGIN
          IF EXISTS (SELECT 1 FROM sales s WHERE s.id = NEW.sale_id AND s.estado = 'aprobado') THEN
            IF NEW.product_id IS DISTINCT FROM OLD.product_id THEN
              UPDATE products SET stock = stock + COALESCE(OLD.qty,0) WHERE id = OLD.product_id;
              SELECT stock INTO _stock FROM products WHERE id = NEW.product_id FOR UPDATE;
              IF COALESCE(_stock,0) < COALESCE(NEW.qty,0) THEN
                RAISE EXCEPTION 'Stock insuficiente (prod %, stock %, req %)', NEW.product_id, _stock, NEW.qty
                  USING ERRCODE = '23514';
              END IF;
              UPDATE products SET stock = stock - COALESCE(NEW.qty,0) WHERE id = NEW.product_id;
            ELSE
              delta := COALESCE(NEW.qty,0) - COALESCE(OLD.qty,0);
              IF delta <> 0 THEN
                IF delta > 0 THEN
                  SELECT stock INTO _stock FROM products WHERE id = NEW.product_id FOR UPDATE;
                  IF COALESCE(_stock,0) < delta THEN
                    RAISE EXCEPTION 'Stock insuficiente (prod %, stock %, req +%)', NEW.product_id, _stock, delta
                      USING ERRCODE = '23514';
                  END IF;
                END IF;
                UPDATE products SET stock = stock - delta WHERE id = NEW.product_id;
              END IF;
            END IF;
          END IF;
          RETURN NULL;
        END $$;

        CREATE OR REPLACE FUNCTION fn_sale_items_ad_stock()
        RETURNS trigger LANGUAGE plpgsql AS $$
        BEGIN
          IF EXISTS (SELECT 1 FROM sales s WHERE s.id = OLD.sale_id AND s.estado = 'aprobado') THEN
            UPDATE products SET stock = stock + COALESCE(OLD.qty,0) WHERE id = OLD.product_id;
          END IF;
          RETURN NULL;
        END $$;

        CREATE TRIGGER trg_sale_items_ai
        AFTER INSERT ON sale_items FOR EACH ROW
        EXECUTE FUNCTION fn_sale_items_ai_stock();

        CREATE TRIGGER trg_sale_items_au
        AFTER UPDATE ON sale_items FOR EACH ROW
        EXECUTE FUNCTION fn_sale_items_au_stock();

        CREATE TRIGGER trg_sale_items_ad
        AFTER DELETE ON sale_items FOR EACH ROW
        EXECUTE FUNCTION fn_sale_items_ad_stock();

        /* ============================================================
         * 4) CAMBIO DE ESTADO EN CABECERAS
         *     - purchases: sumar/restar todo
         *     - sales: validar TODOS los productos antes de descontar
         * ============================================================ */

        CREATE OR REPLACE FUNCTION fn_purchases_au_estado_recalc()
        RETURNS trigger LANGUAGE plpgsql AS $$
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
        END $$;

        CREATE TRIGGER trg_purchases_au_estado
        AFTER UPDATE ON purchases FOR EACH ROW
        EXECUTE FUNCTION fn_purchases_au_estado_recalc();

        CREATE OR REPLACE FUNCTION fn_sales_au_estado_recalc()
        RETURNS trigger LANGUAGE plpgsql AS $$
        DECLARE falta record;
        BEGIN
          IF OLD.estado IS DISTINCT FROM NEW.estado THEN
            IF NEW.estado = 'aprobado' THEN
              -- Validar que ningún producto quede en negativo
              SELECT p.id AS product_id, p.stock, SUM(i.qty) AS requerido
                INTO falta
                FROM sale_items i
                JOIN products p ON p.id = i.product_id
               WHERE i.sale_id = NEW.id
               GROUP BY p.id, p.stock
               HAVING p.stock < SUM(i.qty)
               LIMIT 1;

              IF falta IS NOT NULL THEN
                RAISE EXCEPTION 'Stock insuficiente (prod %, stock %, req %)',
                  falta.product_id, falta.stock, falta.requerido
                  USING ERRCODE = '23514';
              END IF;

              -- Descontar todo en bulk
              UPDATE products p
                 SET stock = p.stock - i.qty
                FROM sale_items i
               WHERE i.product_id = p.id
                 AND i.sale_id = NEW.id;

            ELSIF OLD.estado = 'aprobado' AND NEW.estado <> 'aprobado' THEN
              -- Devolver stock en bulk
              UPDATE products p
                 SET stock = p.stock + i.qty
                FROM sale_items i
               WHERE i.product_id = p.id
                 AND i.sale_id = NEW.id;
            END IF;
          END IF;
          RETURN NULL;
        END $$;

        CREATE TRIGGER trg_sales_au_estado
        AFTER UPDATE ON sales FOR EACH ROW
        EXECUTE FUNCTION fn_sales_au_estado_recalc();

        /* ============================================================
         * 5) BORRADO DE CABECERAS (AJUSTE ÚNICO)
         *     Evita depender de los items y no duplica movimientos.
         * ============================================================ */

        CREATE OR REPLACE FUNCTION fn_purchases_bd_adjust()
        RETURNS trigger LANGUAGE plpgsql AS $$
        BEGIN
          IF OLD.estado = 'aprobado' THEN
            UPDATE products p
               SET stock = p.stock - i.qty
              FROM purchase_items i
             WHERE i.product_id = p.id
               AND i.purchase_id = OLD.id;
          END IF;
          RETURN OLD;
        END $$;

        CREATE TRIGGER trg_purchases_bd_adjust
        BEFORE DELETE ON purchases FOR EACH ROW
        EXECUTE FUNCTION fn_purchases_bd_adjust();

        CREATE OR REPLACE FUNCTION fn_sales_bd_adjust()
        RETURNS trigger LANGUAGE plpgsql AS $$
        BEGIN
          IF OLD.estado = 'aprobado' THEN
            UPDATE products p
               SET stock = p.stock + i.qty
              FROM sale_items i
             WHERE i.product_id = p.id
               AND i.sale_id = OLD.id;
          END IF;
          RETURN OLD;
        END $$;

        CREATE TRIGGER trg_sales_bd_adjust
        BEFORE DELETE ON sales FOR EACH ROW
        EXECUTE FUNCTION fn_sales_bd_adjust();
        SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
        -- Triggers creados por esta migración
        DROP TRIGGER IF EXISTS trg_purchase_items_ai ON purchase_items;
        DROP TRIGGER IF EXISTS trg_purchase_items_au ON purchase_items;
        DROP TRIGGER IF EXISTS trg_purchase_items_ad ON purchase_items;

        DROP TRIGGER IF EXISTS trg_sale_items_ai ON sale_items;
        DROP TRIGGER IF EXISTS trg_sale_items_au ON sale_items;
        DROP TRIGGER IF EXISTS trg_sale_items_ad ON sale_items;

        DROP TRIGGER IF EXISTS trg_purchases_au_estado ON purchases;
        DROP TRIGGER IF EXISTS trg_sales_au_estado     ON sales;

        DROP TRIGGER IF EXISTS trg_purchases_bd_adjust ON purchases;
        DROP TRIGGER IF EXISTS trg_sales_bd_adjust     ON sales;

        -- Funciones creadas por esta migración
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

        -- (No reactivamos los triggers viejos conflictivos)
        SQL);
    }
};
