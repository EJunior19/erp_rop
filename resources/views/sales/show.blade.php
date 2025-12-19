{{-- resources/views/sales/show.blade.php --}}
@extends('layout.admin')

@section('content')
  {{-- Flash messages --}}
  @if (session('success'))
    <div class="mb-4 rounded border border-green-600 bg-green-900/40 text-green-100 px-4 py-3">
      {{ session('success') }}
    </div>
  @endif
  @if (session('error'))
    <div class="mb-4 rounded border border-red-600 bg-red-900/40 text-red-100 px-4 py-3">
      {{ session('error') }}
    </div>
  @endif

  <div class="flex items-center justify-between mb-6">
    <h1 class="text-3xl font-bold text-emerald-400">🧾 Venta #{{ $sale->id }}</h1>
    <div class="flex gap-2">
      <a href="{{ route('sales.index') }}"
         class="px-4 py-2 rounded-lg bg-gray-800 text-white hover:bg-gray-700 transition">
        ← Volver
      </a>

      {{-- Imprimir solo si está aprobado --}}
      @if(($sale->status ?? null) === 'aprobado')
        <a href="{{ route('sales.print', $sale) }}"
           target="_blank"
           class="px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white transition">
          🖨️ Imprimir Ticket
        </a>
      @endif
    </div>
  </div>

  @php
    use Illuminate\Support\Carbon;

    $status = $sale->status ?? $sale->estado ?? '';
    $label = [
      'pendiente_aprobacion' => 'Pendiente de aprobación',
      'aprobado'             => 'Aprobado',
      'rechazado'            => 'Rechazado',
      'cancelado'            => 'Cancelado',
      'editable'             => 'Editable',
    ][$status] ?? ucfirst($status ?: '—');

    $fecha = $sale->fecha
      ? ($sale->fecha instanceof \Carbon\Carbon
          ? $sale->fecha->format('d/m/Y')
          : Carbon::parse($sale->fecha)->format('d/m/Y'))
      : ($sale->created_at?->format('d/m/Y'));

    $estadoBadge = ($status === 'aprobado')
      ? 'bg-green-700'
      : (($status === 'rechazado')
          ? 'bg-red-700'
          : 'bg-yellow-700');

    // ✅ Base factura: si ya existe invoice, tomamos sus totales (más confiable)
    $baseTotal   = (float)($sale->invoice?->total ?? $sale->total ?? 0);
    $baseIva     = (float)($sale->invoice?->tax   ?? $sale->total_iva ?? 0);

    // 🔥 Crédito: total financiado (contado + recargo)
    $financiado  = (($sale->modo_pago ?? null) === 'credito' && !empty($sale->credit_total))
      ? (float)$sale->credit_total
      : null;

    $recargo = ($financiado !== null)
      ? max(0, $financiado - $baseTotal)
      : 0;
  @endphp

  <div class="grid lg:grid-cols-2 gap-6">
    {{-- Info --}}
    <div class="rounded-2xl border border-emerald-600 bg-gray-800 p-6 shadow-lg">
      <h2 class="text-lg font-semibold text-emerald-300 mb-3">📌 Información</h2>

      <dl class="grid grid-cols-2 gap-y-3 text-white">
        <dt class="text-gray-300">Cliente</dt>
        <dd class="font-medium">{{ $sale->client->name ?? '—' }}</dd>

        <dt class="text-gray-300">Modo</dt>
        <dd>
          <span class="px-2 py-1 rounded bg-indigo-600 text-white">
            {{ ucfirst($sale->modo_pago ?? '—') }}
          </span>
        </dd>

        <dt class="text-gray-300">Fecha</dt>
        <dd>{{ $fecha ?? '—' }}</dd>

        <dt class="text-gray-300">Estado</dt>
        <dd>
          <span class="px-2 py-1 rounded text-white {{ $estadoBadge }}">
            {{ $label }}
          </span>
        </dd>

        <dt class="text-gray-300">Nota</dt>
        <dd class="italic">{{ $sale->nota ?? '—' }}</dd>
      </dl>
    </div>

    {{-- Totales --}}
    <div class="rounded-2xl border border-indigo-600 bg-gray-800 p-6 shadow-lg">
      <h2 class="text-lg font-semibold text-indigo-300 mb-3">💰 Totales</h2>

      <div class="space-y-1 text-white">
        <div class="flex justify-between"><span>Gravada 10%:</span><span>Gs. {{ number_format($sale->gravada_10 ?? 0,0,',','.') }}</span></div>
        <div class="flex justify-between"><span>IVA 10%:</span><span>Gs. {{ number_format($sale->iva_10 ?? 0,0,',','.') }}</span></div>
        <div class="flex justify-between"><span>Gravada 5%:</span><span>Gs. {{ number_format($sale->gravada_5 ?? 0,0,',','.') }}</span></div>
        <div class="flex justify-between"><span>IVA 5%:</span><span>Gs. {{ number_format($sale->iva_5 ?? 0,0,',','.') }}</span></div>
        <div class="flex justify-between"><span>Exento:</span><span>Gs. {{ number_format($sale->exento ?? 0,0,',','.') }}</span></div>

        <div class="border-t border-gray-600 my-2"></div>

        <div class="flex justify-between font-semibold">
          <span>Total IVA (base factura):</span>
          <span>Gs. {{ number_format($baseIva,0,',','.') }}</span>
        </div>

        {{-- ✅ Base contado / factura --}}
        <div class="flex justify-between text-xl font-bold text-emerald-400">
          <span>Total factura (base contado):</span>
          <span>Gs. {{ number_format($baseTotal,0,',','.') }}</span>
        </div>

        {{-- ✅ Crédito: financiado + recargo (separado de IVA) --}}
        @if(($sale->modo_pago ?? null) === 'credito' && $financiado !== null)
          <div class="mt-2 space-y-1 text-sm text-gray-300">
            <div class="flex justify-between">
              <span>Total financiado:</span>
              <span class="text-indigo-200 font-semibold">Gs. {{ number_format($financiado,0,',','.') }}</span>
            </div>
            <div class="flex justify-between">
              <span>Recargo/Interés:</span>
              <span class="text-gray-200">Gs. {{ number_format($recargo,0,',','.') }}</span>
            </div>
          </div>
        @endif
      </div>
    </div>
  </div>

  {{-- 🧾 Factura (si existe) --}}
  @if($sale->invoice)
    @php $modo = strtolower($sale->modo_pago ?? 'contado'); @endphp

    <div class="mt-6 rounded-2xl border border-blue-600 bg-gray-800 p-6 shadow-lg">
      <h2 class="text-lg font-semibold text-blue-300 mb-3">🧾 Factura</h2>
      <dl class="grid grid-cols-2 gap-y-3 text-white">
        <dt class="text-gray-300">Número</dt>
        <dd class="font-medium">{{ $sale->invoice->display_number ?? '—' }}</dd>

        <dt class="text-gray-300">Fecha de emisión</dt>
        <dd>{{ optional($sale->invoice->issued_at)->format('d/m/Y') ?? '—' }}</dd>

        <dt class="text-gray-300">Estado</dt>
        <dd>
          <span class="px-2 py-1 rounded bg-green-700 text-white">
            {{ ucfirst($sale->invoice->status ?? 'issued') }}
          </span>
        </dd>

        <dt class="text-gray-300">Total</dt>
        <dd>Gs. {{ number_format($sale->invoice->total ?? 0, 0, ',', '.') }}</dd>
      </dl>

      <div class="mt-4 flex flex-wrap gap-2">
        <a href="{{ route('documents.invoice', $sale->invoice) }}"
           class="px-3 py-2 rounded bg-blue-600 hover:bg-blue-700 text-white">
          🧾 Factura (PDF)
        </a>

        @if($modo === 'contado')
          <a href="{{ route('documents.receipt', $sale->invoice) }}"
             class="px-3 py-2 rounded bg-emerald-600 hover:bg-emerald-700 text-white">
            💵 Recibo
          </a>
        @endif

        @if($modo === 'credito')
          <a href="{{ route('documents.contract', $sale->invoice) }}"
             class="px-3 py-2 rounded bg-indigo-600 hover:bg-indigo-700 text-white">
            🖋️ Contrato
          </a>
          <a href="{{ route('documents.schedule', $sale->invoice) }}"
             class="px-3 py-2 rounded bg-purple-600 hover:bg-purple-700 text-white">
            📆 Cronograma
          </a>
        @endif
      </div>
    </div>
  @endif

  {{-- ⚡ Aprobación --}}
  @if(($status ?? null) !== 'aprobado')
    <div class="mt-8 rounded-2xl border border-emerald-700 bg-gray-900 p-6 shadow-lg">
      <h2 class="text-xl font-semibold text-emerald-400 mb-4">✅ Aprobar venta y generar factura</h2>

      <form method="POST" action="{{ route('sales.approve', $sale) }}" class="space-y-3">
        @csrf

        <div>
          <label class="block text-sm text-gray-300">📅 Fecha de emisión</label>
          <input type="date" name="issued_at" value="{{ now()->toDateString() }}"
                 required class="w-full rounded text-black">
        </div>

        <div>
          <label class="block text-sm text-gray-300">🏷️ Serie (opcional)</label>
          <input type="text" name="series" value="001-001" class="w-full rounded text-black">
        </div>

        <div>
          <label class="block text-sm text-gray-300">🧾 N° factura (opcional)</label>
          <input type="text" name="invoice_number" placeholder="001-001-0000123"
                 class="w-full rounded text-black">
        </div>

        <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded">
          💾 Aprobar y crear factura
        </button>
      </form>
    </div>
  @endif

  {{-- Productos --}}
  <div class="mt-8 rounded-2xl border border-gray-700 bg-gray-900 p-6 shadow-lg">
    <h2 class="text-xl font-semibold text-emerald-400 mb-4">📦 Productos de la venta</h2>
    <div class="overflow-x-auto rounded-lg border border-gray-700">
      <table class="min-w-full text-sm">
        <thead>
          <tr class="text-left text-gray-200 bg-gray-800">
            <th class="px-4 py-2">ID</th>
            <th class="px-4 py-2">Nombre</th>
            <th class="px-4 py-2 text-right">Precio</th>
            <th class="px-4 py-2 text-right">Cant.</th>
            <th class="px-4 py-2">IVA</th>
            <th class="px-4 py-2 text-right">Subtotal</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-zinc-800 text-zinc-200">
          @foreach($sale->items as $it)
            <tr>
              <td class="px-3 py-2">{{ $it->product?->id ?? $it->product_id }}</td>
              <td class="px-3 py-2">{{ $it->product?->name ?? $it->product_name ?? '—' }}</td>
              <td class="px-3 py-2 text-right">Gs. {{ number_format($it->unit_price ?? 0,0,',','.') }}</td>
              <td class="px-3 py-2 text-right">{{ $it->qty ?? 0 }}</td>
              <td class="px-3 py-2">
                {{ ($it->iva_type ?? '')==='exento' ? 'Exento' : ('IVA '.($it->iva_type ?? '—').'%') }}
              </td>
              <td class="px-3 py-2 text-right">Gs. {{ number_format($it->line_total ?? 0,0,',','.') }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>

  {{-- 💳 Detalle del crédito --}}
  @if(($sale->modo_pago ?? null) === 'credito')
    <div class="mt-8 rounded-2xl border border-indigo-700 bg-gray-900 p-6 shadow-lg">
      <h2 class="text-xl font-semibold text-indigo-300 mb-4">💳 Detalle del crédito</h2>

      @php
        $credits = $sale->relationLoaded('credits')
          ? $sale->credits
          : $sale->credits()->with('payments')->get();

        $totalFinanciado = $credits->sum('amount');

        $saldo = $credits->sum(function($c){
          $paid = $c->relationLoaded('payments') ? $c->payments->sum('amount') : 0;
          return max(0, (float)$c->amount - (float)$paid);
        });
      @endphp

      <div class="mb-3 text-gray-300 flex flex-wrap gap-x-6 gap-y-2">
        <div>Total financiado (cuotas): <strong>Gs. {{ number_format($totalFinanciado, 0, ',', '.') }}</strong></div>
        <div>Saldo: <strong>Gs. {{ number_format($saldo, 0, ',', '.') }}</strong></div>
        <div>Cuotas: <strong>{{ $credits->count() }}</strong></div>
      </div>

      @if($credits->isEmpty())
        <div class="p-4 rounded border border-yellow-700/50 bg-yellow-900/20 text-yellow-200">
          No hay cuotas generadas para esta venta.
        </div>
      @else
        <div class="overflow-x-auto rounded-lg border border-gray-700">
          <table class="min-w-full text-sm text-gray-200">
            <thead class="bg-gray-800 text-gray-300">
              <tr>
                <th class="px-3 py-2">#</th>
                <th class="px-3 py-2">Vencimiento</th>
                <th class="px-3 py-2 text-right">Monto</th>
                <th class="px-3 py-2 text-right">Pagado</th>
                <th class="px-3 py-2 text-right">Saldo</th>
                <th class="px-3 py-2 text-center">Estado</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-800">
              @foreach($credits->sortBy('due_date') as $c)
                @php
                  $paid = $c->relationLoaded('payments') ? (float)$c->payments->sum('amount') : 0;
                  $balance = max(0, (float)$c->amount - $paid);

                  $isOverdue = \Carbon\Carbon::parse($c->due_date)->startOfDay()->lt(now()->startOfDay()) && $balance > 0;

                  $badgeClass = $balance == 0
                    ? 'bg-green-700/60 text-green-100'
                    : ($paid > 0
                      ? 'bg-blue-700/60 text-blue-100'
                      : ($isOverdue
                        ? 'bg-red-700/60 text-red-100'
                        : 'bg-yellow-700/60 text-yellow-100'));

                  $badgeText = $balance == 0
                    ? 'Pagado'
                    : ($paid > 0
                      ? 'Parcial'
                      : ($isOverdue ? 'Vencido' : 'Pendiente'));
                @endphp

                <tr>
                  <td class="px-3 py-2">{{ $loop->iteration }}</td>
                  <td class="px-3 py-2">{{ \Carbon\Carbon::parse($c->due_date)->format('d/m/Y') }}</td>
                  <td class="px-3 py-2 text-right">Gs. {{ number_format($c->amount, 0, ',', '.') }}</td>
                  <td class="px-3 py-2 text-right">Gs. {{ number_format($paid, 0, ',', '.') }}</td>
                  <td class="px-3 py-2 text-right">Gs. {{ number_format($balance, 0, ',', '.') }}</td>
                  <td class="px-3 py-2 text-center">
                    <span class="px-2 py-1 rounded {{ $badgeClass }}">{{ $badgeText }}</span>
                  </td>
                </tr>

                @if($c->payments->count())
                  <tr class="bg-gray-900/30">
                    <td colspan="6" class="px-3 py-2">
                      <div class="text-xs text-gray-300">Pagos:</div>
                      <ul class="text-xs text-gray-400 list-disc ml-5">
                        @foreach($c->payments as $p)
                          <li>
                            {{ optional($p->payment_date)->format('d/m/Y') ?? '—' }}
                            — {{ $p->method ?? '—' }}
                            — Gs. {{ number_format($p->amount ?? 0, 0, ',', '.') }}
                          </li>
                        @endforeach
                      </ul>
                    </td>
                  </tr>
                @endif
              @endforeach
            </tbody>
          </table>
        </div>
      @endif
    </div>
  @endif
@endsection
