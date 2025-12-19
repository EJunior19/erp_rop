<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Reemplazar la función para usar sales.status = 'aprobado'
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION public.fn_sale_items_ai_stock()
RETURNS trigger
LANGUAGE plpgsql
AS $function$
DECLARE 
  _stock integer;
BEGIN
  -- ✅ columna correcta en sales: status
  IF EXISTS (
    SELECT 1
      FROM sales s
     WHERE s.id = NEW.sale_id
       AND s.status = 'aprobado'
  ) THEN
    SELECT stock
      INTO _stock
      FROM products
     WHERE id = NEW.product_id
     FOR UPDATE;

    IF COALESCE(_stock,0) < COALESCE(NEW.qty,0) THEN
      RAISE EXCEPTION 'Stock insuficiente (prod %, stock %, req %)', NEW.product_id, _stock, NEW.qty
        USING ERRCODE = '23514';
    END IF;

    UPDATE products
       SET stock = stock - COALESCE(NEW.qty,0)
     WHERE id = NEW.product_id;
  END IF;

  -- AFTER INSERT: el valor retornado no se usa
  RETURN NULL;
END
$function$;
SQL);

        // 2) Asegurar que el trigger exista y llame a la función (sin duplicar)
        DB::unprepared(<<<'SQL'
DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
      FROM pg_trigger t
      JOIN pg_class c ON c.oid = t.tgrelid
     WHERE t.tgname = 'trg_sale_items_ai'
       AND c.relname = 'sale_items'
  ) THEN
    CREATE TRIGGER trg_sale_items_ai
    AFTER INSERT ON public.sale_items
    FOR EACH ROW
    EXECUTE FUNCTION public.fn_sale_items_ai_stock();
  END IF;
END
$$;
SQL);
    }

    public function down(): void
    {
        // (Opcional) Si quieres volver a la versión vieja que usaba 'estado'
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION public.fn_sale_items_ai_stock()
RETURNS trigger
LANGUAGE plpgsql
AS $function$
DECLARE 
  _stock integer;
BEGIN
  -- Versión previa (con 'estado'); mantener por compatibilidad en down()
  IF EXISTS (
    SELECT 1
      FROM sales s
     WHERE s.id = NEW.sale_id
       AND s.estado = 'aprobado'
  ) THEN
    SELECT stock
      INTO _stock
      FROM products
     WHERE id = NEW.product_id
     FOR UPDATE;

    IF COALESCE(_stock,0) < COALESCE(NEW.qty,0) THEN
      RAISE EXCEPTION 'Stock insuficiente (prod %, stock %, req %)', NEW.product_id, _stock, NEW.qty
        USING ERRCODE = '23514';
    END IF;

    UPDATE products
       SET stock = stock - COALESCE(NEW.qty,0)
     WHERE id = NEW.product_id;
  END IF;

  RETURN NULL;
END
$function$;
SQL);
        // Nota: no eliminamos el trigger en down(); solo revertimos la función.
    }
};
