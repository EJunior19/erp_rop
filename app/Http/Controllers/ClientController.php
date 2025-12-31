<?php

// app/Http/Controllers/ClientController.php
namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $q      = trim($request->get('q'));
        $status = $request->get('status', 'all'); // active/inactive/all
        $test   = $request->get('test', 'all');   // prod/test/all
        $per    = (int) $request->get('per_page', 25);

        $clients = Client::query()
            ->when($q, function ($query) use ($q) {
                $query->where(function ($w) use ($q) {
                    $w->where('name', 'ILIKE', "%{$q}%")
                    ->orWhere('email', 'ILIKE', "%{$q}%")
                    ->orWhere('phone', 'ILIKE', "%{$q}%")
                    ->orWhere('code', 'ILIKE', "%{$q}%")
                    ->orWhere('ruc', 'ILIKE', "%{$q}%");
                });
            })
            ->when($status === 'active', fn ($qq) => $qq->where('active', true))
            ->when($status === 'inactive', fn ($qq) => $qq->where('active', false))
            ->when($test === 'test', fn ($qq) => $qq->where('is_test', true))
            ->when($test === 'prod', fn ($qq) => $qq->where('is_test', false))
            ->orderBy('id', 'desc')
            ->paginate($per)
            ->withQueryString();


            if ($request->ajax()) {
                return response()->json([
                    'tbody' => view('clients._table', compact('clients'))->render(),
                    'pagination' => view('clients._pagination', compact('clients'))->render(),
                ]);
            }

            return view('clients.index', compact('clients'));

                    return view('clients.index', compact('clients'));
                }

    // Papelera (opcional, si usás SoftDeletes)
    public function deleted()
    {
        $clients = Client::onlyTrashed()->latest()->paginate(12);
        return view('clients.deleted', compact('clients'));
    }

    public function create()
    {
        // Preview sin consumir la secuencia (PostgreSQL)
        try {
            $row = DB::selectOne("
                SELECT last_value + increment_by AS next_id
                FROM pg_sequences
                WHERE schemaname='public' AND sequencename='clients_id_seq'
            ");
            $nextId = $row?->next_id ?? null;
            $code   = $nextId ? sprintf('CLI-%05d', $nextId) : null;
        } catch (\Throwable $e) {
            // En otros drivers no mostramos preview
            $nextId = null;
            $code   = null;
        }

        return view('clients.create', compact('nextId','code'));
    }
        public function store(Request $request)
        {
            $validated = $request->validate([
                'name'    => ['required','string','max:255'],
                'ruc'     => ['required','string','max:20','unique:clients,ruc'],
                'email'   => ['required','email','max:255','unique:clients,email'],
                'phone'   => ['nullable','string','max:50'],
                'address' => ['nullable','string','max:255'],
                'notes'   => ['nullable','string'],
                'active'  => ['required','boolean'],
            ]);

            $validated['user_id'] = Auth::id(); // creador/propietario
            $client = Client::create($validated);

            // Ver si viene del botón “Guardar + Documentos”
            if ($request->input('action') === 'save_docs') {
                return redirect()
                    ->route('clients.edit', [$client, 'tab' => 'docs'])
                    ->with('success', "Cliente {$client->name} creado. Ahora podés agregar los documentos.");
            }

            // Caso normal
            return redirect()->route('clients.index')
                ->with('success', "Cliente {$client->name} creado (código {$client->code}).");
        }

        public function show(Client $client)
        {
            return view('clients.show', compact('client'));
        }

        public function edit(Client $client)
        {
            return view('clients.edit', compact('client'));
        }

    public function update(Request $request, Client $client)
    {
        $validated = $request->validate([
            'name'    => ['required','string','max:255'],
            'email'   => ['required','email','max:255',"unique:clients,email,{$client->id}"],
            'phone'   => ['nullable','string','max:50'],
            'address' => ['nullable','string','max:255'],
            'notes'   => ['nullable','string'],
            'active'  => ['required','boolean'],
        ]);

        $client->update($validated);

        return redirect()->route('clients.show',$client)
            ->with('success','Cliente actualizado.');
    }

    public function destroy(Client $client)
    {
        // si usás soft deletes: mueve a papelera
        $client->delete();

        return redirect()->route('clients.index')
            ->with('success','Cliente eliminado.');
    }

    // Activar/Desactivar (toggle 'active')
    public function activate(Client $client)
    {
        $client->update(['active' => ! $client->active]);
        return back()->with('success', $client->active ? 'Cliente activado.' : 'Cliente desactivado.');
    }

    /**
     * API de búsqueda para typeahead
     * GET /api/clients?q=texto
     */
    public function search(Request $request)
{
    $q = trim((string) $request->query('q', ''));
    if ($q === '') {
        return response()->json([]);
    }

    $driver = DB::connection()->getDriverName(); // 'pgsql','mysql','sqlite',...
    $like   = $driver === 'pgsql' ? 'ILIKE' : 'LIKE';

    // Detecta columnas opcionales
    $hasRuc = Schema::hasColumn('clients', 'ruc');
    $hasDoc = Schema::hasColumn('clients', 'document');

    $rows = Client::query()
        ->where('active', true) // 👈 Solo clientes activos
        ->where(function ($w) use ($q, $like, $hasRuc, $hasDoc) {
            $w->where('name',  $like, "%{$q}%")
              ->orWhere('email',$like, "%{$q}%");

            if ($hasRuc) $w->orWhere('ruc', $like, "%{$q}%");
            if ($hasDoc) $w->orWhere('document', $like, "%{$q}%");

            // búsqueda por ID exacto si escriben un número
            if (ctype_digit($q)) {
                $w->orWhere('id', (int)$q);
            }
        })
        ->orderBy('name')
        ->limit(10);

    // Selecciona solo columnas existentes
    $cols = ['id','name','email'];
    if (Schema::hasColumn('clients','phone')) $cols[] = 'phone';
    if ($hasRuc) $cols[] = 'ruc';
    if ($hasDoc) $cols[] = 'document';

    return response()->json($rows->get($cols));
}

}
