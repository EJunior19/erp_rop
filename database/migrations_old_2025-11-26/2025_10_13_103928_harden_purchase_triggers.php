<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Auditoría: columna updated_by (si no existe)
        if (!Schema::hasColumn('purchases', 'updated_by')) {
            DB::statement("ALTER TABLE purchases ADD COLUMN updated_by INTEGER NULL;");
        }

        // 2) Índice único PARCIAL: evita duplicados de movimientos SOLO para compras
        //    (no afecta ref_type='sale', 'adjust', etc.)
        DB::statement(<<<'SQL'
DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_indexes
    WHERE schemaname = 'public'
      AND indexname  = 'uniq_mov_purchase_only'
  ) THEN
    CREATE UNIQUE INDEX uniq_mov_purchase_only
      ON inventory_movements (ref_id, product_id)
      WHERE ref_type = 'purchase';
  END IF;
END$$;
SQL);

        // 3) Función: aprobar => sumar stock + registrar movimientos (idempotente).
        //    Bloquear “desaprobar” (política ERP).
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION public.fn_purchases_au_estado_recalc()
RETURNS trigger AS $$
BEGIN
  -- Actúa SOLO si el estado cambia
  IF OLD.estado IS DISTINCT FROM NEW.estado THEN

    -- ✅ Pasa a aprobado: sumar stock + insertar movimientos
    IF NEW.estado = 'aprobado' THEN
      -- Sumar stock
      UPDATE products p
         SET stock = p.stock + i.qty
        FROM purchase_items i
       WHERE i.product_id = p.id
         AND i.purchase_id = NEW.id;

      -- Movimientos (uno por ítem). ON CONFLICT evita duplicar si reintentan.
      INSERT INTO inventory_movements
        (product_id, type, qty, ref_type, ref_id, note, user_id, created_at)
      SELECT
        i.product_id, 'in', i.qty, 'purchase', NEW.id,
        'Compra aprobada',
        COALESCE(NEW.updated_by, NULL),
        now()
      FROM purchase_items i
      WHERE i.purchase_id = NEW.id
      ON CONFLICT ON CONSTRAINT uniq_mov_purchase_only DO NOTHING;

    -- 🚫 Intento de “desaprobar” una compra ya aprobada
    ELSIF OLD.estado = 'aprobado' AND NEW.estado <> 'aprobado' THEN
      RAISE EXCEPTION 'No se puede cambiar el estado de una compra ya aprobada. Use un ajuste/anulación.';
    END IF;

  END IF;

  -- AFTER trigger: el retorno se ignora (puede ser NULL)
  RETURN NULL;
END
$$ LANGUAGE plpgsql;
SQL);

        // 4) Trigger AFTER UPDATE OF estado
        DB::unprepared(<<<'SQL'
DROP TRIGGER IF EXISTS trg_purchases_au_estado ON purchases;
CREATE TRIGGER trg_purchases_au_estado
AFTER UPDATE OF estado ON purchases
FOR EACH ROW
EXECUTE FUNCTION public.fn_purchases_au_estado_recalc();
SQL);

        // 5) Función: bloquear borrado de compras aprobadas
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION public.fn_purchases_bd_block_approved()
RETURNS trigger AS $$
BEGIN
  IF OLD.estado = 'aprobado' THEN
    RAISE EXCEPTION 'No se puede eliminar una compra aprobada. Use anulación con ajuste.';
  END IF;
  RETURN OLD;
END
$$ LANGUAGE plpgsql;
SQL);

        // 6) Trigger BEFORE DELETE (bloqueo)
        DB::unprepared(<<<'SQL'
DROP TRIGGER IF EXISTS trg_purchases_bd_block ON purchases;
CREATE TRIGGER trg_purchases_bd_block
BEFORE DELETE ON purchases
FOR EACH ROW
EXECUTE FUNCTION public.fn_purchases_bd_block_approved();
SQL);
    }

    public function down(): void
    {
        // Quitar triggers
        DB::unprepared("DROP TRIGGER IF EXISTS trg_purchases_au_estado ON purchases;");
        DB::unprepared("DROP TRIGGER IF EXISTS trg_purchases_bd_block ON purchases;");

        // Quitar funciones
        DB::unprepared("DROP FUNCTION IF EXISTS public.fn_purchases_au_estado_recalc();");
        DB::unprepared("DROP FUNCTION IF EXISTS public.fn_purchases_bd_block_approved();");

        // Quitar índice único parcial
        DB::statement(<<<'SQL'
DO $$
BEGIN
  IF EXISTS (
    SELECT 1 FROM pg_indexes
    WHERE schemaname='public' AND indexname='uniq_mov_purchase_only'
  ) THEN
    DROP INDEX uniq_mov_purchase_only;
  END IF;
END$$;
SQL);

        // (Opcional) Eliminar columna updated_by
        // DB::statement("ALTER TABLE purchases DROP COLUMN IF EXISTS updated_by;");
    }
};
