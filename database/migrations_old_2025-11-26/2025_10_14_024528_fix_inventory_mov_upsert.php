<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
{
    // 1) Deduplicar SOLO movimientos de compras (por si hubiera repetidos)
    DB::unprepared("
        WITH dups AS (
            SELECT id
            FROM (
                SELECT
                    id,
                    ROW_NUMBER() OVER (
                        PARTITION BY ref_type, ref_id, product_id
                        ORDER BY id
                    ) AS rn
                FROM inventory_movements
                WHERE ref_type = 'purchase'
            ) t
            WHERE t.rn > 1
        )
        DELETE FROM inventory_movements im
        USING dups
        WHERE im.id = dups.id;
    ");

    // 2) Borrar el índice previo si existe (por si quedó a medias)
    DB::unprepared("DROP INDEX IF EXISTS inv_mov_uniq_ref;");

    // 3) Crear índice único PARCIAL: solo aplica a 'purchase'
    DB::unprepared("
        CREATE UNIQUE INDEX IF NOT EXISTS inv_mov_uniq_purchase_ref
        ON inventory_movements (ref_type, ref_id, product_id)
        WHERE ref_type = 'purchase';
    ");

    // 4) (Opcional pero recomendado) Re-crear la función del trigger por si no la tienes actualizada
    DB::unprepared("
    CREATE OR REPLACE FUNCTION public.fn_pr_update_approve_guard()
    RETURNS trigger
    LANGUAGE plpgsql
    AS $$
    DECLARE
      v_total_items int;
      v_full_items  int;
    BEGIN
      -- Evitar doble aprobación desde BD
      IF NEW.status = 'aprobado' THEN
        RAISE EXCEPTION 'La recepción ya está aprobada';
      END IF;

      -- Marcar aprobado + sello
      NEW.status      := 'aprobado';
      NEW.approved_by := COALESCE(NEW.approved_by, NEW.received_by);
      NEW.approved_at := COALESCE(NEW.approved_at, NOW());

      -- Movimientos (uno por producto de esta recepción)
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
      ON CONFLICT (ref_type, ref_id, product_id) DO NOTHING;

      -- Actualizar stock por producto
      UPDATE products p
      SET stock = p.stock + x.qty_sum
      FROM (
        SELECT pri.product_id, SUM(pri.received_qty) AS qty_sum
        FROM purchase_receipt_items pri
        WHERE pri.purchase_receipt_id = NEW.id
        GROUP BY pri.product_id
      ) AS x
      WHERE p.id = x.product_id;

      -- Si todos los ítems de la OC están completos, marcar OC como 'recibido'
      SELECT COUNT(*) INTO v_total_items
      FROM purchase_order_items poi
      WHERE poi.purchase_order_id = NEW.purchase_order_id;

      SELECT COUNT(*) INTO v_full_items
      FROM purchase_order_items poi
      WHERE poi.purchase_order_id = NEW.purchase_order_id
        AND NOT EXISTS (
          SELECT 1
          FROM purchase_receipts r
          JOIN purchase_receipt_items ri ON ri.purchase_receipt_id = r.id
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
    $$;
    ");
}


    public function down(): void
    {
        // (Opcional) revertir cambios
        DB::unprepared("DROP INDEX IF EXISTS inv_mov_uniq_ref;");
        // No borramos la función; si quieres, aquí podrías restaurar la versión anterior.
    }
};
