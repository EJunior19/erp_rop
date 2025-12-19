<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1) INSERT (ya estaba bien, pero lo re-dejamos consistente)
        DB::statement(<<<'SQL'
CREATE OR REPLACE FUNCTION public.fn_sale_items_ai_stock()
RETURNS trigger
LANGUAGE plpgsql
AS $function$
DECLARE
  _stock integer;
BEGIN
  IF EXISTS (
    SELECT 1 FROM sales s
     WHERE s.id = NEW.sale_id
       AND s.status = 'aprobado'
  ) THEN
    SELECT stock INTO _stock
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

        // 2) UPDATE (ANTES usaba estado, ahora status ✅)
        DB::statement(<<<'SQL'
CREATE OR REPLACE FUNCTION public.fn_sale_items_au_stock()
RETURNS trigger
LANGUAGE plpgsql
AS $function$
DECLARE
  delta integer;
  _stock integer;
BEGIN
  IF EXISTS (
    SELECT 1 FROM sales s
     WHERE s.id = NEW.sale_id
       AND s.status = 'aprobado'
  ) THEN

    IF NEW.product_id IS DISTINCT FROM OLD.product_id THEN
      -- devuelve stock del producto anterior
      UPDATE products
         SET stock = stock + COALESCE(OLD.qty,0)
       WHERE id = OLD.product_id;

      -- valida stock del nuevo producto
      SELECT stock INTO _stock
        FROM products
       WHERE id = NEW.product_id
       FOR UPDATE;

      IF COALESCE(_stock,0) < COALESCE(NEW.qty,0) THEN
        RAISE EXCEPTION 'Stock insuficiente (prod %, stock %, req %)', NEW.product_id, _stock, NEW.qty
          USING ERRCODE = '23514';
      END IF;

      -- descuenta del nuevo producto
      UPDATE products
         SET stock = stock - COALESCE(NEW.qty,0)
       WHERE id = NEW.product_id;

    ELSE
      delta := COALESCE(NEW.qty,0) - COALESCE(OLD.qty,0);

      IF delta <> 0 THEN
        IF delta > 0 THEN
          SELECT stock INTO _stock
            FROM products
           WHERE id = NEW.product_id
           FOR UPDATE;

          IF COALESCE(_stock,0) < delta THEN
            RAISE EXCEPTION 'Stock insuficiente (prod %, stock %, req +%)', NEW.product_id, _stock, delta
              USING ERRCODE = '23514';
          END IF;
        END IF;

        UPDATE products
           SET stock = stock - delta
         WHERE id = NEW.product_id;
      END IF;
    END IF;

  END IF;

  RETURN NULL;
END
$function$;
SQL);

        // 3) DELETE (ANTES usaba estado, ahora status ✅)
        DB::statement(<<<'SQL'
CREATE OR REPLACE FUNCTION public.fn_sale_items_ad_stock()
RETURNS trigger
LANGUAGE plpgsql
AS $function$
BEGIN
  IF EXISTS (
    SELECT 1 FROM sales s
     WHERE s.id = OLD.sale_id
       AND s.status = 'aprobado'
  ) THEN
    UPDATE products
       SET stock = stock + COALESCE(OLD.qty,0)
     WHERE id = OLD.product_id;
  END IF;

  RETURN NULL;
END
$function$;
SQL);
    }

    public function down(): void
    {
        // No hago rollback automático de funciones (porque podrías tener versiones anteriores)
    }
};
