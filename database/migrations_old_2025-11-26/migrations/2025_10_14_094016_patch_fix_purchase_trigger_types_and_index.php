<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::beginTransaction();
        try {
            // 1) Asegurar índice ÚNICO parcial SOLO para compras
            DB::unprepared(<<<SQL
                -- Borra índice global si existe (el que falló)
                DROP INDEX IF EXISTS public.inv_mov_uniq_ref;

                -- Crea (o asegura) el índice parcial correcto para compras
                CREATE UNIQUE INDEX IF NOT EXISTS inv_mov_uniq_purchase_ref
                ON public.inventory_movements (ref_type, ref_id, product_id)
                WHERE (ref_type)::text = 'purchase'::text;
            SQL);

            // 2) Regrabar función de aprobación usando type='entrada'
            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION public.fn_pr_update_approve_guard()
                RETURNS trigger
                LANGUAGE plpgsql
                AS $$
                DECLARE
                  v_total_items int;
                  v_full_items  int;
                BEGIN
                  -- Idempotencia
                  IF OLD.status = 'aprobado' THEN
                    RAISE EXCEPTION 'La recepción ya está aprobada';
                  END IF;

                  -- Solo desde pendiente_aprobacion
                  IF OLD.status <> 'pendiente_aprobacion' THEN
                    RAISE EXCEPTION 'Solo se puede aprobar desde pendiente_aprobacion (actual: %)', OLD.status;
                  END IF;

                  -- Auditoría
                  NEW.approved_by := COALESCE(NEW.approved_by, NEW.received_by);
                  NEW.approved_at := COALESCE(NEW.approved_at, NOW());

                  -- Movimientos (mantener vocabulario de la tabla: 'entrada')
                  INSERT INTO public.inventory_movements (
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
                  FROM public.purchase_receipt_items pri
                  WHERE pri.purchase_receipt_id = NEW.id
                    AND pri.received_qty > 0
                  ON CONFLICT ON CONSTRAINT inv_mov_uniq_purchase_ref DO NOTHING;

                  -- Actualizar stock
                  UPDATE public.products p
                  SET stock = p.stock + x.qty_sum
                  FROM (
                    SELECT pri.product_id, SUM(pri.received_qty)::int AS qty_sum
                    FROM public.purchase_receipt_items pri
                    WHERE pri.purchase_receipt_id = NEW.id
                    GROUP BY pri.product_id
                  ) x
                  WHERE p.id = x.product_id;

                  -- Cerrar OC si todos los renglones quedaron completos (solo recepciones aprobadas)
                  SELECT COUNT(*) INTO v_total_items
                  FROM public.purchase_order_items poi
                  WHERE poi.purchase_order_id = NEW.purchase_order_id;

                  SELECT COUNT(*) INTO v_full_items
                  FROM public.purchase_order_items poi
                  WHERE poi.purchase_order_id = NEW.purchase_order_id
                    AND NOT EXISTS (
                      SELECT 1
                      FROM public.purchase_receipts r
                      JOIN public.purchase_receipt_items ri ON ri.purchase_receipt_id = r.id
                      WHERE r.purchase_order_id = NEW.purchase_order_id
                        AND r.status = 'aprobado'
                        AND ri.product_id = poi.product_id
                      GROUP BY ri.product_id
                      HAVING SUM(ri.received_qty) < poi.quantity
                    );

                  IF v_total_items > 0 AND v_full_items = v_total_items THEN
                    UPDATE public.purchase_orders
                    SET status = 'recibido', updated_at = NOW()
                    WHERE id = NEW.purchase_order_id
                      AND status IN ('borrador','enviado');
                  END IF;

                  RETURN NEW;
                END;
                $$;
            SQL);

            // 3) Re-crear trigger con WHEN correcto (por si tu migración anterior falló a medias)
            DB::unprepared(<<<SQL
                DROP TRIGGER IF EXISTS trg_pr_update_approve_guard ON public.purchase_receipts;

                CREATE TRIGGER trg_pr_update_approve_guard
                BEFORE UPDATE OF status ON public.purchase_receipts
                FOR EACH ROW
                WHEN (OLD.status IS DISTINCT FROM NEW.status AND NEW.status::text = 'aprobado'::text)
                EXECUTE FUNCTION public.fn_pr_update_approve_guard();
            SQL);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function down(): void
{
    DB::beginTransaction();
    try {
        // Nota: si quisieras restaurar el estado anterior por completo, acá podrías
        // regrabar tu función previa y re-crear el índice global.
        // Para simplificar, solo restauramos el trigger a la forma anterior.

        DB::unprepared(<<<SQL
            DROP TRIGGER IF EXISTS trg_pr_update_approve_guard ON public.purchase_receipts;

            CREATE TRIGGER trg_pr_update_approve_guard
            BEFORE UPDATE OF status ON public.purchase_receipts
            FOR EACH ROW
            WHEN (NEW.status::text = 'aprobado'::text)
            EXECUTE FUNCTION public.fn_pr_update_approve_guard();
        SQL);

        DB::commit();
    } catch (\Throwable $e) {
        DB::rollBack();
        throw $e;
    }
}

};
