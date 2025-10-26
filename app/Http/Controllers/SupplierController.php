<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Models\PurchaseItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Database\UniqueConstraintViolationException; // Laravel 12+
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use App\Models\SupplierAddress;
use App\Models\SupplierPhone;
use App\Models\SupplierEmail;

class SupplierController extends Controller
{
    /** Listado */
    public function index()
    {
        $suppliers = \App\Models\Supplier::query()
            ->select('suppliers.*')
            // Email principal (prioriza tipo 'compras', luego default)
            ->selectSub("
                SELECT e.email
                FROM supplier_emails e
                WHERE e.supplier_id = suppliers.id
                AND (e.is_active IS TRUE OR e.is_active IS NULL)
                ORDER BY (e.type = 'compras') DESC, e.is_default DESC, e.id ASC
                LIMIT 1
            ", 'email_main')
            // Teléfono principal
            ->selectSub("
                SELECT p.phone_number
                FROM supplier_phones p
                WHERE p.supplier_id = suppliers.id
                AND (p.is_active IS TRUE OR p.is_active IS NULL)
                ORDER BY p.is_primary DESC, p.id ASC
                LIMIT 1
            ", 'phone_main')
            ->latest('id')
            ->paginate(12);

        return view('suppliers.index', compact('suppliers'));
    }

    /** Form de creación */
    public function create()
    {
        return view('suppliers.create');
    }

    /** Crear proveedor */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'    => ['required','string','max:255'],
            'ruc'     => ['nullable','string','max:50','unique:suppliers,ruc'],
            'phone'   => ['nullable','string','max:50'],
            'email'   => ['nullable','email','max:255'],
            'address' => ['nullable','string','max:255'],
            'notes'   => ['nullable','string'],
            'active'  => ['required','boolean'],
        ]);

        try {
            $supplier = Supplier::create($validated);
            $supplier->refresh(); // para leer 'code' si lo genera un trigger

            return redirect()
                ->route('suppliers.show', $supplier)
                ->with('success', 'Proveedor ' . $supplier->name . ' creado (código ' . ($supplier->code ?? '—') . ').');
        } catch (UniqueConstraintViolationException $e) {
            return back()
                ->withErrors(['ruc' => 'Ese RUC ya está registrado.'])
                ->withInput();
        } catch (QueryException $e) {
            if (str_contains(strtolower($e->getMessage()), 'unique') && str_contains($e->getMessage(), 'ruc')) {
                return back()->withErrors(['ruc' => 'Ese RUC ya está registrado.'])->withInput();
            }
            throw $e;
        }
    }
    

    /** Detalle enriquecido */
    public function show(Supplier $supplier)
    {
        // Relaciones principales (1 a 1) para mostrar en la cabecera
        $supplier->load([
            'mainEmail:id,supplier_id,email,type,is_default',
            'primaryPhone:id,supplier_id,phone_number,is_primary',
            'primaryAddress:id,supplier_id,street,city,state,country,is_primary',

            // Colecciones completas ordenadas (para las tablas de abajo)
            'addresses' => fn($q) => $q->orderByDesc('is_primary')->orderBy('id'),
            'phones'    => fn($q) => $q->orderByDesc('is_primary')->orderBy('id'),
            'emails'    => fn($q) => $q->orderByDesc('is_default')->orderBy('id'),
        ]);

        // Cantidad de compras aprobadas
        $supplier->loadCount([
            'purchases as purchases_count' => fn($q) => $q->where('estado', 'aprobado'),
        ]);

        // Totales (suma de cantidades y monto) solo con compras aprobadas
        $totals = \App\Models\PurchaseItem::query()
            ->join('purchases', 'purchase_items.purchase_id', '=', 'purchases.id')
            ->where('purchases.supplier_id', $supplier->id)
            ->where('purchases.estado', 'aprobado')
            ->selectRaw('COALESCE(SUM(purchase_items.qty), 0) AS total_items')
            ->selectRaw('COALESCE(SUM(purchase_items.qty * purchase_items.cost), 0) AS total_amount')
            ->first();

        // Últimas 5 compras (items_count = sum(qty); total = sum(qty*cost) por compra)
        // IMPORTANTE: en Purchase debe existir: items() { return $this->hasMany(PurchaseItem::class); }
        $latestPurchases = $supplier->purchases()
            ->latest('id')
            ->withSum('items as items_count', 'qty')
            ->addSelect([
                'total' => \App\Models\PurchaseItem::selectRaw('COALESCE(SUM(qty * cost), 0)')
                    ->whereColumn('purchase_items.purchase_id', 'purchases.id')
            ])
            ->take(5)
            ->get(['purchases.id','purchases.estado','purchases.purchased_at','purchases.created_at']);

        return view('suppliers.show', compact('supplier', 'totals', 'latestPurchases'));
    }


    /** Form de edición */
    public function edit(Supplier $supplier)
    {
        return view('suppliers.edit', compact('supplier'));
    }

    /** Actualizar proveedor */
    public function update(Request $request, Supplier $supplier)
    {
        $validated = $request->validate([
            'name'    => ['required','string','max:255'],
            'ruc'     => ['nullable','string','max:50', Rule::unique('suppliers','ruc')->ignore($supplier->id)],
            'phone'   => ['nullable','string','max:50'],
            'email'   => ['nullable','email','max:255'],
            'address' => ['nullable','string','max:255'],
            'notes'   => ['nullable','string'],
            'active'  => ['required','boolean'],
        ]);

        try {
            $supplier->update($validated);

            return redirect()
                ->route('suppliers.show', $supplier)
                ->with('success', 'Proveedor actualizado con éxito.');
        } catch (UniqueConstraintViolationException $e) {
            return back()
                ->withErrors(['ruc' => 'Ese RUC ya está registrado.'])
                ->withInput();
        } catch (QueryException $e) {
            if (str_contains(strtolower($e->getMessage()), 'unique') && str_contains($e->getMessage(), 'ruc')) {
                return back()->withErrors(['ruc' => 'Ese RUC ya está registrado.'])->withInput();
            }
            throw $e;
        }
    }

    /** Eliminar (soft o hard según tu modelo) */
    public function destroy(Supplier $supplier)
    {
        $supplier->delete();

        return redirect()
            ->route('suppliers.index')
            ->with('success','Proveedor eliminado');
}
/** Autocomplete JSON */
public function search(Request $request)
{
    $q = trim($request->query('q', ''));
    if ($q === '') {
        return response()->json([]);
    }

    $driver = DB::connection()->getDriverName();  // mysql, pgsql, sqlite...
    $like   = $driver === 'pgsql' ? 'ILIKE' : 'LIKE';
    $needle = "%{$q}%";

    $suppliers = DB::table('suppliers as s')
        ->leftJoin('supplier_phones as p', function ($join) {
            $join->on('p.supplier_id', '=', 's.id')
                 ->where('p.is_primary', true)
                 ->where('p.is_active', true);
        })
        ->leftJoin('supplier_addresses as a', function ($join) {
            $join->on('a.supplier_id', '=', 's.id')
                 ->where('a.is_primary', true);
        })
        ->leftJoin('supplier_emails as e', 'e.supplier_id', '=', 's.id')
        ->where('s.active', true)
        ->where(function ($w) use ($like, $needle, $q) {
            $w->where('s.name', $like, $needle)
              ->orWhere('s.ruc', $like, $needle);

            if (ctype_digit($q)) {
                $w->orWhere('s.id', (int) $q);
            }
        })
        ->select(
            's.id',
            's.name',
            's.ruc',
            DB::raw('COALESCE(p.phone_number, \'\') as phone'),
            DB::raw('COALESCE(e.email, \'\') as email'),
            DB::raw('COALESCE(a.street, \'\') as address')
        )
        ->orderBy('s.name')
        ->limit(10)
        ->get();

    return response()->json($suppliers);
}

 
        // ================== GESTIÓN DE TELÉFONOS Y DIRECCIONES ==================
        public function storeAddress(Request $request, Supplier $supplier)
        {
            $data = $request->validate([
                'street'      => 'required|string|max:255',
                'city'        => 'required|string|max:120',
                'state'       => 'nullable|string|max:120',
                'country'     => 'nullable|string|max:120',
                'postal_code' => 'nullable|string|max:20',
                'type'        => ['required', Rule::in(['fiscal','entrega','sucursal'])],
                'is_primary'  => 'nullable|boolean',
            ]);

            // si marca principal, desmarca las demás
            if (!empty($data['is_primary'])) {
                $supplier->addresses()->update(['is_primary' => false]);
                $data['is_primary'] = true;
            }

            $supplier->addresses()->create($data);
            return back()->with('ok', 'Dirección agregada.');
        }

        public function destroyAddress(Supplier $supplier, SupplierAddress $address)
        {
            abort_unless($address->supplier_id === $supplier->id, 404);
            $address->delete();
            return back()->with('ok','Dirección eliminada.');
        }

        public function setPrimaryAddress(Supplier $supplier, SupplierAddress $address)
        {
            abort_unless($address->supplier_id === $supplier->id, 404);
            $supplier->addresses()->update(['is_primary' => false]);
            $address->update(['is_primary' => true]);
            return back()->with('ok','Dirección principal actualizada.');
        }

        public function storePhone(Request $request, Supplier $supplier)
        {
            $data = $request->validate([
                'phone_number' => 'required|string|max:30',
                'type'         => ['required', Rule::in(['principal','secundario','fax','whatsapp'])],
                'is_active'    => 'nullable|boolean',
                'is_primary'   => 'nullable|boolean',
            ]);

            if (!empty($data['is_primary'])) {
                $supplier->phones()->update(['is_primary' => false]);
                $data['is_primary'] = true;
            }

            // evita duplicado por proveedor
            if ($supplier->phones()->where('phone_number', $data['phone_number'])->exists()) {
                return back()->withErrors(['phone_number' => 'Este número ya está cargado para el proveedor.'])->withInput();
            }

            $supplier->phones()->create($data);
            return back()->with('ok','Teléfono agregado.');
        }

        public function destroyPhone(Supplier $supplier, SupplierPhone $phone)
        {
            abort_unless($phone->supplier_id === $supplier->id, 404);
            $phone->delete();
            return back()->with('ok','Teléfono eliminado.');
        }

        public function setPrimaryPhone(Supplier $supplier, SupplierPhone $phone)
        {
            abort_unless($phone->supplier_id === $supplier->id, 404);
            $supplier->phones()->update(['is_primary' => false]);
            $phone->update(['is_primary' => true]);
            return back()->with('ok','Teléfono principal actualizado.');
        }

        // ================== GESTIÓN DE EMAILS ==================
        public function storeEmail(Request $request, Supplier $supplier)
        {
            $data = $request->validate([
                'email'      => 'required|email:rfc,dns|max:150',
                'type'       => ['required', Rule::in(['general','ventas','compras','facturacion'])],
                'is_active'  => 'nullable|boolean',
                'is_default' => 'nullable|boolean',
            ]);

            // Evitar duplicados por proveedor
            if ($supplier->emails()->where('email', $data['email'])->exists()) {
                return back()->withErrors(['email' => 'Este correo ya está cargado para el proveedor.'])->withInput();
            }

            // Si marcó como default, desmarcamos otros del MISMO tipo
            if (!empty($data['is_default'])) {
                $supplier->emails()->where('type', $data['type'])->update(['is_default' => false]);
                $data['is_default'] = true;
            }

            $supplier->emails()->create($data);
            return back()->with('ok','Correo agregado.');
        }

        public function destroyEmail(Supplier $supplier, SupplierEmail $email)
        {
            abort_unless($email->supplier_id === $supplier->id, 404);
            $email->delete();
            return back()->with('ok','Correo eliminado.');
        }

        public function setDefaultEmail(Supplier $supplier, SupplierEmail $email)
        {
            abort_unless($email->supplier_id === $supplier->id, 404);
            // Un default por tipo
            $supplier->emails()->where('type', $email->type)->update(['is_default' => false]);
            $email->update(['is_default' => true]);
            return back()->with('ok','Correo principal actualizado.');
        }


}
