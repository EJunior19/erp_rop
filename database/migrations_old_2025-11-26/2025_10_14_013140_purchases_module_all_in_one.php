<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Helper: cálculo de subtotal + status en purchase_receipt_items
        DB::unprepared(<<<'SQL'
        -- DROP previos (idempotente)
        DROP TRIGGER IF EXISTS trg_pri_biu_calc ON purchase_receipt_items;
        DROP FUNCTION IF EXISTS fn_pri_biu_calc();

        CREATE OR REPLACE FUNCTION fn_pri_biu_calc()
        RETURNS trigger
        LANGUAGE plpgsql
        AS $$
        BEGIN
          NEW.ordered_qty  := COALESCE(NEW.ordered_qty, 0);
          NEW.received_qty := COALESCE(NEW.received_qty, 0);
          NEW.unit_cost    := COALESCE(NEW.unit_cost, 0);

          NEW.subtotal := ROUND((NEW.received_qty::numeric * NEW.unit_cost::numeric)::numeric, 2);

          IF NEW.received_qty <= 0 THEN
            NEW.status := 'faltante';
          ELSIF NEW.received_qty >= NEW.ordered_qty THEN
            NEW.status := 'completo';
          ELSE
            NEW.status := 'parcial';
          END IF;

          RETURN NEW;
        END
        $$;

        CREATE TRIGGER trg_pri_biu_calc
        BEFORE INSERT OR UPDATE OF ordered_qty, received_qty, unit_cost
        ON purchase_receipt_items
        FOR EACH ROW
        EXECUTE FUNCTION fn_pri_biu_calc();
        SQL);

        // 2) Sincronizar estado de ítems de la OC según recepciones aprobadas
        DB::unprepared(<<<'SQL'
        DROP FUNCTION IF EXISTS fn_po_item_sync_status(bigint);

        CREATE OR REPLACE FUNCTION fn_po_item_sync_status(p_purchase_order_id bigint)
        RETURNS void
        LANGUAGE plpgsql
        AS $$
        BEGIN
          UPDATE purchase_order_items poi
          SET status = CASE
              WHEN COALESCE(agg.sum_recv,0) >= poi.quantity THEN 'recibido'
              WHEN COALESCE(agg.sum_recv,0) = 0            THEN 'pendiente'
              ELSE 'faltante'
            END
          FROM (
            SELECT pri.product_id,
                   SUM(pri.received_qty) AS sum_recv
            FROM purchase_receipt_items pri
            JOIN purchase_receipts pr ON pr.id = pri.purchase_receipt_id
            WHERE pr.purchase_order_id = p_purchase_order_id
              AND pr.status = 'aprobado'
            GROUP BY pri.product_id
          ) agg
          WHERE poi.purchase_order_id = p_purchase_order_id
            AND poi.product_id = agg.product_id;

          -- Ítems sin ninguna recepción aprobada -> pendiente
          UPDATE purchase_order_items poi
          SET status = 'pendiente'
          WHERE poi.purchase_order_id = p_purchase_order_id
            AND NOT EXISTS (
              SELECT 1
              FROM purchase_receipt_items pri
              JOIN purchase_receipts pr ON pr.id = pri.purchase_receipt_id
              WHERE pr.purchase_order_id = p_purchase_order_id
                AND pr.status = 'aprobado'
                AND pri.product_id = poi.product_id
            );
        END
        $$;
        SQL);

        // 3) Sincronizar estado de la OC (cabecera)
        DB::unprepared(<<<'SQL'
        DROP FUNCTION IF EXISTS fn_po_sync_status(bigint);

        CREATE OR REPLACE FUNCTION fn_po_sync_status(p_purchase_order_id bigint)
        RETURNS void
        LANGUAGE plpgsql
        AS $$
        DECLARE
          all_count   int;
          full_count  int;
          any_recv    int;
        BEGIN
          PERFORM fn_po_item_sync_status(p_purchase_order_id);

          SELECT COUNT(*) INTO all_count
          FROM purchase_order_items
          WHERE purchase_order_id = p_purchase_order_id;

          SELECT COUNT(*) INTO full_count
          FROM purchase_order_items
          WHERE purchase_order_id = p_purchase_order_id
            AND status = 'recibido';

          SELECT COUNT(*) INTO any_recv
          FROM purchase_receipts pr
          WHERE pr.purchase_order_id = p_purchase_order_id
            AND pr.status = 'aprobado';

          IF all_count > 0 AND full_count = all_count THEN
            UPDATE purchase_orders
            SET status = 'recibido'
            WHERE id = p_purchase_order_id;
          ELSIF any_recv > 0 THEN
            UPDATE purchase_orders
            SET status = 'enviado'
            WHERE id = p_purchase_order_id
              AND status IN ('borrador','enviado');
          END IF;
        END
        $$;
        SQL);

        // 4) Guard de aprobación: valida contra OC, crea movimientos, sube stock, marca approved_at, sincroniza OC
        DB::unprepared(<<<'SQL'
        DROP TRIGGER IF EXISTS trg_pr_update_approve_guard ON purchase_receipts;
        DROP FUNCTION IF EXISTS fn_pr_update_approve_guard();

        CREATE OR REPLACE FUNCTION fn_pr_update_approve_guard()
        RETURNS trigger
        LANGUAGE plpgsql
        AS $$
        DECLARE
          v_order_id bigint;
          r_item record;
          v_ordenada int;
          v_recibida_aprobada int;
        BEGIN
          -- Solo cuando cambia a APROBADO
          IF TG_OP = 'UPDATE'
             AND NEW.status = 'aprobado'
             AND COALESCE(OLD.status,'') <> 'aprobado'
          THEN
            v_order_id := NEW.purchase_order_id;

            -- Validación: no exceder lo ordenado (por producto)
            FOR r_item IN
              SELECT pri.id AS pri_id,
                     pri.product_id,
                     pri.received_qty
              FROM purchase_receipt_items pri
              WHERE pri.purchase_receipt_id = NEW.id
            LOOP
              SELECT COALESCE(SUM(quantity),0) INTO v_ordenada
              FROM purchase_order_items
              WHERE purchase_order_id = v_order_id
                AND product_id = r_item.product_id;

              SELECT COALESCE(SUM(pri2.received_qty),0) INTO v_recibida_aprobada
              FROM purchase_receipt_items pri2
              JOIN purchase_receipts pr2 ON pr2.id = pri2.purchase_receipt_id
              WHERE pr2.purchase_order_id = v_order_id
                AND pr2.status = 'aprobado'
                AND pri2.product_id = r_item.product_id;

              IF (v_recibida_aprobada + r_item.received_qty) > v_ordenada THEN
                RAISE EXCEPTION
                  'No se puede aprobar: el producto % excede lo ordenado (aprobado previo %, actual %, ordenado %)',
                  r_item.product_id, v_recibida_aprobada, r_item.received_qty, v_ordenada
                  USING ERRCODE = '23514';
              END IF;
            END LOOP;

            -- Normalizar y forzar cálculo de líneas (por si tocaron fuera del app)
            UPDATE purchase_receipt_items pri
            SET ordered_qty  = COALESCE(ordered_qty, 0),
                received_qty = COALESCE(received_qty, 0),
                unit_cost    = COALESCE(unit_cost, 0)
            WHERE pri.purchase_receipt_id = NEW.id;

            UPDATE purchase_receipt_items pri
            SET ordered_qty = ordered_qty
            WHERE pri.purchase_receipt_id = NEW.id;

            -- Movimientos de inventario (1 por línea) + stock
            INSERT INTO inventory_movements
              (product_id, type, reason, user_id, ref_type, ref_id, qty, note, created_at, updated_at)
            SELECT
              pri.product_id,
              'entrada',
              'Compra recibida',
              NEW.approved_by,
              'purchase',
              pri.id,
              pri.received_qty,
              'Receipt #'||NEW.id||' ('||NEW.receipt_number||') OC #'||v_order_id,
              NOW(), NOW()
            FROM purchase_receipt_items pri
            WHERE pri.purchase_receipt_id = NEW.id
              AND pri.received_qty > 0
            ON CONFLICT (ref_id, product_id) DO NOTHING;

            UPDATE products p
            SET stock = p.stock + agg.sum_qty
            FROM (
              SELECT pri.product_id, SUM(pri.received_qty) AS sum_qty
              FROM purchase_receipt_items pri
              WHERE pri.purchase_receipt_id = NEW.id
              GROUP BY pri.product_id
            ) agg
            WHERE p.id = agg.product_id;

            -- Marca fecha de aprobación si no vino
            IF NEW.approved_at IS NULL THEN
              NEW.approved_at := NOW();
            END IF;

            -- Sync estados de la OC
            PERFORM fn_po_sync_status(v_order_id);
          END IF;

          RETURN NEW;
        END
        $$;

        CREATE TRIGGER trg_pr_update_approve_guard
        BEFORE UPDATE ON purchase_receipts
        FOR EACH ROW
        EXECUTE FUNCTION fn_pr_update_approve_guard();
        SQL);

        // 5) (Opcional) Bloquear “desaprobación”
        DB::unprepared(<<<'SQL'
        DROP TRIGGER IF EXISTS trg_pr_block_unapprove ON purchase_receipts;
        DROP FUNCTION IF EXISTS fn_pr_block_unapprove();

        CREATE OR REPLACE FUNCTION fn_pr_block_unapprove()
        RETURNS trigger
        LANGUAGE plpgsql
        AS $$
        BEGIN
          IF OLD.status = 'aprobado' AND NEW.status <> 'aprobado' THEN
            RAISE EXCEPTION 'No se puede cambiar el estado desde APROBADO a %', NEW.status
              USING ERRCODE = '23503';
          END IF;
          RETURN NEW;
        END
        $$;

        CREATE TRIGGER trg_pr_block_unapprove
        BEFORE UPDATE ON purchase_receipts
        FOR EACH ROW
        EXECUTE FUNCTION fn_pr_block_unapprove();
        SQL);
    }

    public function down(): void
    {
        // Borrar triggers/funciones en orden inverso
        DB::unprepared(<<<'SQL'
        -- Block unapprove
        DROP TRIGGER IF EXISTS trg_pr_block_unapprove ON purchase_receipts;
        DROP FUNCTION IF EXISTS fn_pr_block_unapprove();

        -- Approval guard
        DROP TRIGGER IF EXISTS trg_pr_update_approve_guard ON purchase_receipts;
        DROP FUNCTION IF EXISTS fn_pr_update_approve_guard();

        -- Sync functions
        DROP FUNCTION IF EXISTS fn_po_sync_status(bigint);
        DROP FUNCTION IF EXISTS fn_po_item_sync_status(bigint);

        -- Receipt item calc
        DROP TRIGGER IF EXISTS trg_pri_biu_calc ON purchase_receipt_items;
        DROP FUNCTION IF EXISTS fn_pri_biu_calc();
        SQL);
    }
};
