  @extends('layout.admin')

  @section('content')
  <div class="w-full px-6 text-gray-200">
    {{-- Breadcrumb + Título + Acciones --}}
    <div class="flex items-center justify-between mb-6">
      <div>
        <a href="{{ route('purchase_orders.index') }}"
          class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-gray-700 text-gray-300 hover:bg-gray-800">
          ← Volver a Órdenes
        </a>
        <h1 class="mt-3 text-3xl font-bold text-green-400 flex items-center gap-3">
          🧾 OC {{ $purchase_order->order_number }}
          @php
            $status = $purchase_order->status;
            $chip = [
              'borrador' => 'bg-gray-700 text-gray-100',
              'enviado'  => 'bg-blue-700 text-blue-100',
              'recibido' => 'bg-emerald-700 text-emerald-100',
              'cerrado'  => 'bg-purple-700 text-purple-100',
            ][$status] ?? 'bg-gray-700 text-gray-100';
          @endphp
          <span class="text-xs px-2 py-1 rounded {{ $chip }}">{{ ucfirst($status) }}</span>
        </h1>
        <p class="text-gray-400 text-sm mt-1">
          Creado: {{ $purchase_order->created_at?->format('d/m/Y H:i') }} ·
          Actualizado: {{ $purchase_order->updated_at?->format('d/m/Y H:i') }}
        </p>
      </div>

      <div class="flex flex-wrap gap-2">
        {{-- Crear recepción (preseleccionando OC si tu create lo admite por querystring) --}}
        <a href="{{ route('purchase_receipts.create') }}?purchase_order_id={{ $purchase_order->id }}"
          class="px-4 py-2 rounded-lg bg-sky-600 hover:bg-sky-700 font-semibold shadow">
          ➕ Registrar recepción
        </a>

        @if (Route::has('purchase_orders.edit'))
          <a href="{{ route('purchase_orders.edit', $purchase_order) }}"
            class="px-4 py-2 rounded-lg bg-gray-700 hover:bg-gray-600 font-semibold shadow">
            ✏️ Editar
          </a>
        @endif

        @if (Route::has('purchase_orders.destroy'))
          <form method="POST" action="{{ route('purchase_orders.destroy', $purchase_order) }}"
                onsubmit="return confirm('¿Eliminar esta OC? Esta acción no se puede deshacer.')">
            @csrf @method('DELETE')
            <button class="px-4 py-2 rounded-lg bg-rose-700 hover:bg-rose-800 font-semibold shadow">
              🗑️ Eliminar
            </button>
          </form>
        @endif
      </div>
    </div>

    {{-- Flash --}}
    <x-flash-message />

    {{-- Tarjeta: Datos de la OC --}}
    <div class="bg-gray-900 border border-gray-700 rounded-xl p-6 mb-8">
      <div class="grid md:grid-cols-2 gap-6 text-base">
        <p><span class="text-green-300 font-semibold">Proveedor:</span> {{ $purchase_order->supplier?->name ?? '—' }}</p>
        <p><span class="text-green-300 font-semibold">RUC:</span> {{ $purchase_order->supplier?->ruc ?? '—' }}</p>
        <p><span class="text-green-300 font-semibold">Fecha:</span> {{ \Illuminate\Support\Carbon::parse($purchase_order->order_date)->format('d/m/Y') }}</p>
        <p><span class="text-green-300 font-semibold">Entrega estimada:</span>
          {{ optional($purchase_order->expected_date)->format('d/m/Y') ?? '—' }}
        </p>
        <p class="md:col-span-2">
          <span class="text-green-300 font-semibold">Notas:</span>
          {{ $purchase_order->notes ?: '—' }}
        </p>
      </div>

      {{-- Extra del proveedor (si tienes normalización cargada) --}}
      @php
        $primaryPhone   = $purchase_order->supplier?->phones()?->where('is_primary', true)->first();
        $primaryAddress = $purchase_order->supplier?->addresses()?->where('is_primary', true)->first();
        $defaultEmail   = $purchase_order->supplier?->emails()?->where('is_default', true)->first();
      @endphp
      <div class="grid md:grid-cols-3 gap-6 mt-6 text-sm">
        <div class="bg-gray-800/60 rounded-lg p-4 border border-gray-700">
          <h3 class="text-gray-300 font-semibold mb-2">📧 Email (por defecto)</h3>
          <p>{{ $defaultEmail?->email ?? $purchase_order->supplier?->email ?? '—' }}</p>
        </div>
        <div class="bg-gray-800/60 rounded-lg p-4 border border-gray-700">
          <h3 class="text-gray-300 font-semibold mb-2">📞 Teléfono (principal)</h3>
          <p>{{ $primaryPhone?->phone_number ?? $purchase_order->supplier?->phone ?? '—' }}</p>
        </div>
        <div class="bg-gray-800/60 rounded-lg p-4 border border-gray-700">
          <h3 class="text-gray-300 font-semibold mb-2">📍 Dirección (principal)</h3>
          <p>
            @if($primaryAddress)
              {{ $primaryAddress->street }}, {{ $primaryAddress->city }}
              @if($primaryAddress->state) - {{ $primaryAddress->state }} @endif
              · {{ $primaryAddress->country }}
              @if($primaryAddress->postal_code) (CP {{ $primaryAddress->postal_code }}) @endif
            @else
              {{ $purchase_order->supplier?->address ?? '—' }}
            @endif
          </p>
        </div>
      </div>
    </div>

    {{-- Ítems de la OC --}}
    <div class="bg-gray-900 border border-gray-700 rounded-xl mb-8">
      <div class="flex items-center justify-between p-4">
        <h2 class="font-semibold text-green-300">🧩 Ítems de la orden</h2>
        <div class="text-sm text-gray-400">
          Cant. total: <span class="font-semibold text-gray-200">
            {{ $purchase_order->items->sum('quantity') }}
          </span>
          · Ítems: <span class="font-semibold text-gray-200">
            {{ $purchase_order->items->count() }}
          </span>
        </div>
      </div>

      <div class="overflow-x-auto border-t border-gray-700">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-800 text-gray-300 uppercase text-xs">
            <tr>
              <th class="text-left px-4 py-2">Producto</th>
              <th class="text-left px-4 py-2">Código</th>
              <th class="text-right px-4 py-2">Cantidad</th>
              <th class="text-right px-4 py-2">P. unit.</th>
              <th class="text-right px-4 py-2">Subtotal</th>
              <th class="text-left px-4 py-2">Estado</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-700">
            @foreach($purchase_order->items as $it)
              <tr class="hover:bg-gray-800/60">
                <td class="px-4 py-2">{{ $it->product?->name ?? '—' }}</td>
                <td class="px-4 py-2 font-mono text-gray-300">{{ $it->product?->code ?? '—' }}</td>
                <td class="px-4 py-2 text-right">{{ (int) $it->quantity }}</td>
                <td class="px-4 py-2 text-right">₲ {{ number_format($it->unit_price, 0, ',', '.') }}</td>
                <td class="px-4 py-2 text-right">₲ {{ number_format($it->subtotal, 0, ',', '.') }}</td>
                <td class="px-4 py-2">
                  @php
                    $rec = (int) ($receivedByProduct[$it->product_id] ?? 0);   // recibida
                    $ped = (int) $it->quantity;                                // pedida
                    $estadoItem = $rec === 0 ? 'faltante' : ($rec >= $ped ? 'completo' : 'parcial');

                    $chipIt = [
                      'completo' => 'bg-emerald-700 text-emerald-100',
                      'parcial'  => 'bg-amber-700 text-amber-100',
                      'faltante' => 'bg-rose-700 text-rose-100',
                    ][$estadoItem];
                  @endphp

                  <span class="text-xs px-2 py-1 rounded {{ $chipIt }}">{{ ucfirst($estadoItem) }}</span>
                  <span class="ml-2 text-xs text-gray-400">({{ $rec }}/{{ $ped }})</span>
                </td>

              </tr>
            @endforeach
          </tbody>
          <tfoot class="bg-gray-800 text-sm">
            <tr>
              <td colspan="4" class="px-4 py-3 text-right font-semibold">Total</td>
              <td class="px-4 py-3 text-right font-bold text-green-300">
                ₲ {{ number_format($purchase_order->total, 0, ',', '.') }}
              </td>
              <td></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>

    {{-- Recepciones relacionadas --}}
    <div class="bg-gray-900 border border-gray-700 rounded-xl">
      <div class="flex items-center justify-between p-4">
        <h2 class="font-semibold text-green-300">🚛 Recepciones registradas</h2>
        <a href="{{ route('purchase_receipts.index') }}"
          class="text-sm px-3 py-1 rounded border border-gray-700 text-gray-300 hover:bg-gray-800">
          Ver todas
        </a>
      </div>

      @php
        // Si el controlador no lo cargó, lo traemos acá de forma segura
        $receipts = $purchase_order->relationLoaded('receipts')
          ? $purchase_order->receipts
          : $purchase_order->receipts()->withCount('items')->latest('id')->get();
      @endphp

      <div class="overflow-x-auto border-t border-gray-700">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-800 text-gray-300 uppercase text-xs">
            <tr>
              <th class="px-4 py-2 text-left">#</th>
              <th class="px-4 py-2 text-left">N° recepción</th>
              <th class="px-4 py-2 text-left">Fecha</th>
              <th class="px-4 py-2 text-left">Ítems</th>
              <th class="px-4 py-2 text-left">Estado</th>
              <th class="px-4 py-2 text-right">Acciones</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-700">
            @forelse($receipts as $r)
              <tr class="hover:bg-gray-800/60">
                <td class="px-4 py-2">{{ $r->id }}</td>
                <td class="px-4 py-2 font-mono">{{ $r->receipt_number }}</td>
                <td class="px-4 py-2">
                  {{ \Illuminate\Support\Carbon::parse($r->received_date)->format('d/m/Y') }}
                </td>
                <td class="px-4 py-2">{{ (int) ($r->items_count ?? $r->items()->count()) }}</td>
                <td class="px-4 py-2">
                  @php
                    $rs = $r->status;
                    $chipR = [
                      'borrador'             => 'bg-gray-700 text-gray-100',
                      'pendiente_aprobacion' => 'bg-amber-700 text-amber-100',
                      'aprobado'             => 'bg-emerald-700 text-emerald-100',
                      'rechazado'            => 'bg-rose-700 text-rose-100',
                    ][$rs] ?? 'bg-gray-700 text-gray-100';
                  @endphp
                  <span class="text-xs px-2 py-1 rounded {{ $chipR }}">{{ ucfirst(str_replace('_',' ',$rs)) }}</span>
                </td>
                <td class="px-4 py-2 text-right">
                  <div class="inline-flex gap-2">
                    @if (Route::has('purchase_receipts.show'))
                      <a href="{{ route('purchase_receipts.show', $r) }}"
                        class="px-3 py-1 rounded border border-gray-600 text-gray-300 hover:bg-gray-700">
                        Ver
                      </a>
                    @endif

                    @if ($r->status === 'pendiente_aprobacion' && Route::has('purchase_receipts.approve'))
                      <form method="POST" action="{{ route('purchase_receipts.approve', $r) }}"
                            onsubmit="return confirm('¿Aprobar recepción y actualizar stock?')">
                        @csrf
                        <button class="px-3 py-1 rounded bg-emerald-700 hover:bg-emerald-800 text-white">
                          Aprobar
                        </button>
                      </form>
                    @endif

                    @if ($r->status === 'pendiente_aprobacion' && Route::has('purchase_receipts.reject'))
                      <form method="POST" action="{{ route('purchase_receipts.reject', $r) }}"
                            onsubmit="return confirm('¿Rechazar recepción?')">
                        @csrf
                        <button class="px-3 py-1 rounded bg-rose-700 hover:bg-rose-800 text-white">
                          Rechazar
                        </button>
                      </form>
                    @endif
                  </div>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="6" class="px-4 py-6 text-center text-gray-400">No hay recepciones registradas para esta OC.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    {{-- Barra inferior de acciones rápidas --}}
    <div class="mt-8 flex flex-wrap gap-3">
      <a href="{{ route('purchase_orders.index') }}"
        class="px-6 py-2 rounded-lg border border-gray-600 text-gray-300 hover:bg-gray-700 font-semibold shadow">
        ← Volver
      </a>

      <a href="{{ route('purchase_receipts.create') }}?purchase_order_id={{ $purchase_order->id }}"
        class="px-6 py-2 rounded-lg bg-sky-600 hover:bg-sky-700 text-white font-semibold shadow">
        Registrar recepción
      </a>

      @if (Route::has('purchase_orders.edit'))
        <a href="{{ route('purchase_orders.edit', $purchase_order) }}"
          class="px-6 py-2 rounded-lg bg-gray-700 hover:bg-gray-600 font-semibold shadow">
          Editar OC
        </a>
      @endif
    </div>
  </div>
  @endsection
