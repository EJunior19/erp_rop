<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // 0) Unique parcial para no duplicar movimientos por (ref_type, ref_id, product_id)
        DB::unprepared(<<<'SQL'
DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_indexes
    WHERE tablename = 'inventory_movements'
      AND indexname = 'uniq_mov_purchase_only'
  ) THEN
    CREATE UNIQUE INDEX uniq_mov_purchase_only
      ON inventory_movements (ref_type, ref_id, product_id)
      WHERE ref_type = 'purchase';
  END IF;
END $$;
SQL);

        // 1) Función corregida (usa OLD.status para evitar el falso positivo)
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION public.fn_pr_update_approve_guard()
RETURNS trigger
LANGUAGE plpgsql
AS $function$
DECLARE
  v_total_items int;
  v_full_items  int;
BEGIN
  -- Si ya estaba aprobada antes del cambio, impedirlo
  IF OLD.status = 'aprobado' THEN
    RAISE EXCEPTION 'La recepción ya está aprobada';
  END IF;

  -- Sellos de aprobación (por si el controller no los estableció)
  NEW.status      := 'aprobado';
  NEW.approved_by := COALESCE(NEW.approved_by, NEW.received_by);
  NEW.approved_at := COALESCE(NEW.approved_at, NOW());

  -- Movimientos de inventario (uno por producto de esta recepción)
  INSERT INTO inventory_movements (
      product_id, type, reason, user_id,
      ref_type, ref_id, qty, note, created_at, updated_at
  )
  SELECT
      pri.product_id,
      'entrada',
      'Compra recibida',
      COALESCE(NEW.approved_by, NEW.received_by),
      'purchase',
      NEW.id,
      pri.received_qty,
      'Receipt #'||NEW.id||' (OC #'||NEW.purchase_order_id||')',
      NOW(), NOW()
  FROM purchase_receipt_items pri
  WHERE pri.purchase_receipt_id = NEW.id
    AND pri.received_qty > 0
  ON CONFLICT ON CONSTRAINT uniq_mov_purchase_only DO NOTHING;

  -- Actualiza stock agregado por producto (el trigger solo corre al aprobar)
  UPDATE products p
  SET stock = p.stock + x.qty_sum
  FROM (
    SELECT pri.product_id, SUM(pri.received_qty) AS qty_sum
    FROM purchase_receipt_items pri
    WHERE pri.purchase_receipt_id = NEW.id
    GROUP BY pri.product_id
  ) AS x
  WHERE p.id = x.product_id;

  -- Si toda la OC quedó completa, marcarla como 'recibido'
  SELECT COUNT(*) INTO v_total_items
  FROM purchase_order_items poi
  WHERE poi.purchase_order_id = NEW.purchase_order_id;

  SELECT COUNT(*) INTO v_full_items
  FROM purchase_order_items poi
  WHERE poi.purchase_order_id = NEW.purchase_order_id
    AND NOT EXISTS (
      SELECT 1
      FROM purchase_receipts r
      JOIN purchase_receipt_items ri
        ON ri.purchase_receipt_id = r.id
      WHERE r.purchase_order_id = NEW.purchase_order_id
        AND ri.product_id = poi.product_id
      GROUP BY ri.product_id
      HAVING SUM(ri.received_qty) < poi.quantity
    );

  IF v_total_items > 0 AND v_full_items = v_total_items THEN
    UPDATE purchase_orders
    SET status = 'recibido', updated_at = NOW()
    WHERE id = NEW.purchase_order_id
      AND status IN ('borrador','enviado');
  END IF;

  RETURN NEW;
END;
$function$;
SQL);

        // 2) Re-crear el trigger para que SOLO dispare cuando cambie a 'aprobado'
        DB::unprepared(<<<'SQL'
DROP TRIGGER IF EXISTS trg_pr_update_approve_guard ON public.purchase_receipts;

CREATE TRIGGER trg_pr_update_approve_guard
BEFORE UPDATE OF status
ON public.purchase_receipts
FOR EACH ROW
WHEN (NEW.status = 'aprobado')
EXECUTE FUNCTION public.fn_pr_update_approve_guard();
SQL);
    }

    public function down(): void
    {
        // Quita trigger y función (dejas el índice porque es útil; quítalo si no lo quieres)
        DB::unprepared("DROP TRIGGER IF EXISTS trg_pr_update_approve_guard ON public.purchase_receipts;");
        DB::unprepared("DROP FUNCTION IF EXISTS public.fn_pr_update_approve_guard();");
        // Si quieres revertir también el índice, descomenta:
        // DB::unprepared("DROP INDEX IF EXISTS uniq_mov_purchase_only;");
    }
};
