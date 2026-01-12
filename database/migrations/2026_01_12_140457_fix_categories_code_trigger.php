<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
-- 1) Borrar triggers/funciones viejas (las 2 versiones)
DROP TRIGGER IF EXISTS trg_categories_set_code ON public.categories;
DROP TRIGGER IF EXISTS trg_categories_set_code_upd ON public.categories;

DROP FUNCTION IF EXISTS public.tg_categories_set_code();
DROP FUNCTION IF EXISTS public.tg_categories_set_code_seq();
DROP FUNCTION IF EXISTS public.tg_categories_set_code_seq();

-- 2) Crear función definitiva (CAT-0001 basado en ID)
CREATE OR REPLACE FUNCTION public.tg_categories_set_code_seq()
RETURNS trigger
LANGUAGE plpgsql
AS $$
DECLARE
  next_id bigint;
BEGIN
  -- Si ya viene code, respetar
  IF NEW.code IS NOT NULL AND btrim(NEW.code) <> '' THEN
    RETURN NEW;
  END IF;

  -- Tomar el próximo ID de la secuencia
  next_id := nextval('categories_id_seq');

  -- Forzar el ID y generar el code
  NEW.id := next_id;
  NEW.code := 'CAT-' || lpad(next_id::text, 4, '0');

  RETURN NEW;
END;
$$;

-- 3) Crear trigger BEFORE INSERT
CREATE TRIGGER trg_categories_set_code
BEFORE INSERT ON public.categories
FOR EACH ROW
EXECUTE FUNCTION public.tg_categories_set_code_seq();

-- 4) Homologar categorías existentes (si tenían PANTALONES etc.)
UPDATE public.categories
SET code = 'CAT-' || lpad(id::text, 4, '0')
WHERE code IS NULL OR code !~ '^CAT-[0-9]{4}$';
SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
DROP TRIGGER IF EXISTS trg_categories_set_code ON public.categories;
DROP FUNCTION IF EXISTS public.tg_categories_set_code_seq();
SQL);
    }
};
