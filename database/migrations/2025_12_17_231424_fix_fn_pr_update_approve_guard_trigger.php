<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION fn_pr_update_approve_guard()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
  INSERT INTO public.inventory_movements (
    product_id, type, reason, user_id, ref_type, ref_id,
    quantity, note, created_at, updated_at
  )
  SELECT
    pri.product_id,
    'entrada',
    'Compra recibida',
    COALESCE(NEW.approved_by, NEW.received_by),
    'purchase',
    NEW.id,
    pri.received_qty,
    'Receipt #' || NEW.id || COALESCE(' (OC #' || NEW.purchase_order_id || ')', ''),
    NOW(),
    NOW()
  FROM public.purchase_receipt_items pri
  WHERE pri.purchase_receipt_id = NEW.id
    AND pri.received_qty > 0
  ON CONFLICT ON CONSTRAINT inv_mov_uniq_purchase_ref DO NOTHING;

  RETURN NEW;
END;
$$;
SQL);
    }

    public function down(): void
    {
        // No hay un "down" universal sin saber tu versión anterior.
        // Podés dejar vacío o restaurar la versión anterior si la tenés guardada.
    }
};
