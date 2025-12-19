<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
-- Aseguramos que la función del trigger NO mueva stock
CREATE OR REPLACE FUNCTION public.fn_purchases_au_estado_recalc()
RETURNS trigger AS $$
BEGIN
  -- Sincroniza estados/controles de la OC, pero NO toca inventory_movements
  BEGIN
    PERFORM public.fn_po_item_sync_status(NEW.id);
  EXCEPTION
    WHEN undefined_function THEN
      -- Si aún no existe fn_po_item_sync_status, no romper el flujo
      RAISE NOTICE 'fn_po_item_sync_status no existe; continuar sin sync.';
  END;

  RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Recreamos el trigger solo para cambios a estado aprobado/a
DROP TRIGGER IF EXISTS trg_purchases_au_estado ON public.purchases;

CREATE TRIGGER trg_purchases_au_estado
AFTER UPDATE OF estado ON public.purchases
FOR EACH ROW
WHEN (
  OLD.estado IS DISTINCT FROM NEW.estado
  AND NEW.estado IN ('aprobada','aprobado')
)
EXECUTE FUNCTION public.fn_purchases_au_estado_recalc();
SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
DROP TRIGGER IF EXISTS trg_purchases_au_estado ON public.purchases;
-- La función la dejamos creada (es inofensiva y puede ser reutilizada).
SQL);
    }
};
