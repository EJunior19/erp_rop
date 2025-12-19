@extends('layout.admin')

@section('content')
@php
  $fmt = fn($n) => 'Gs. '.number_format((int)$n, 0, ',', '.');
  $days = $credit->due_date ? now()->startOfDay()->diffInDays($credit->due_date, false) : null;
  $statusColor = $credit->status === 'pagado' ? 'bg-emerald-600/15 text-emerald-300 border-emerald-700/40'
                : ($credit->status === 'vencido' ? 'bg-rose-600/15 text-rose-300 border-rose-700/40'
                : 'bg-amber-600/15 text-amber-300 border-amber-700/40');
  $venceStr = is_null($days) ? '—'
            : ($days < 0 ? 'hace '.abs($days).' día'.(abs($days)==1?'':'s')
            : ($days === 0 ? 'hoy' : 'en '.$days.' día'.($days==1?'':'s')));
  $pagado = (int)$credit->amount - (int)$credit->balance;
@endphp

<div class="flex items-center justify-between mb-6">
  <h1 class="text-2xl font-bold text-slate-100">
    📄 Detalle del Crédito <span class="font-mono">#{{ $credit->id }}</span>
  </h1>

  <div class="flex gap-2">
    @if(Route::has('credits.print'))
      <a href="{{ route('credits.print', $credit) }}"
         class="px-4 py-2 rounded-lg border border-slate-600 bg-slate-800 text-slate-100 hover:bg-slate-700 transition">
        🖨️ Imprimir
      </a>
    @endif
    <a href="{{ route('credits.index') }}"
       class="px-4 py-2 rounded-lg border border-slate-600 bg-slate-800 text-slate-100 hover:bg-slate-700 transition">
      ← Volver
    </a>
  </div>
</div>

<x-flash-message />

{{-- ======= KPIs ======= --}}
<div class="grid grid-cols-1 lg:grid-cols-4 gap-4 mb-6">
  <div class="bg-slate-900 border border-slate-700 rounded-xl p-4">
    <div class="text-slate-400 text-xs uppercase">Cliente</div>
    <div class="text-slate-100 font-semibold mt-1">{{ $credit->client->name ?? '—' }}</div>
    @if(!empty($credit->client?->phone) || !empty($credit->client?->email))
      <div class="text-slate-400 text-xs mt-2">
        {{ $credit->client->phone ?? '' }} {{ $credit->client->email ? ' · '.$credit->client->email : '' }}
      </div>
    @endif
  </div>

  <div class="bg-slate-900 border border-slate-700 rounded-xl p-4">
    <div class="text-slate-400 text-xs uppercase">Monto</div>
    <div class="text-slate-100 font-semibold mt-1">{{ $fmt($credit->amount) }}</div>
    <div class="text-slate-400 text-xs mt-2">Venta: #{{ $credit->sale->id ?? '—' }}</div>
  </div>

  <div class="bg-slate-900 border border-slate-700 rounded-xl p-4">
    <div class="text-slate-400 text-xs uppercase">Saldo</div>
    <div class="mt-1 font-semibold {{ $credit->balance > 0 ? 'text-amber-300' : 'text-emerald-300' }}">
      {{ $fmt($credit->balance) }}
    </div>
    <div class="text-slate-400 text-xs mt-2">Pagado: {{ $fmt($pagado) }}</div>
  </div>

  <div class="bg-slate-900 border border-slate-700 rounded-xl p-4">
    <div class="text-slate-400 text-xs uppercase">Vence</div>
    <div class="text-slate-100 font-semibold mt-1">
      {{ $credit->due_date?->format('Y-m-d') ?? '—' }}
    </div>
    <div class="inline-flex items-center gap-2 text-xs mt-2 px-2 py-1 rounded border {{ $statusColor }}">
      <span class="font-medium">{{ ucfirst($credit->status) }}</span>
      <span class="text-slate-400">•</span>
      <span>{{ $venceStr }}</span>
    </div>
  </div>
</div>

{{-- ======= Datos extra ======= --}}
<div class="bg-slate-900 text-slate-200 rounded-xl shadow-lg border border-slate-700 p-5 mb-6">
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    <div>
      <div class="text-slate-400 text-xs uppercase mb-1">Dirección del cliente</div>
      <div class="text-slate-100">{{ $credit->client->address ?? '—' }}</div>
    </div>

    <div>
      <div class="text-slate-400 text-xs uppercase mb-1">N° de factura</div>
      <div class="text-slate-100">
        @php
          $inv = $credit->sale?->invoice;
        @endphp

        @if($inv && $inv->number)
          {{-- Ejemplo: 001-001-0000001 --}}
          {{ $inv->series }}-{{ str_pad($inv->number, 7, '0', STR_PAD_LEFT) }}
        @else
          —
        @endif
      </div>
    </div>

    <div>
      <div class="text-slate-400 text-xs uppercase mb-1">Creado / Actualizado</div>
      <div class="text-slate-100">
        {{ $credit->created_at?->format('Y-m-d H:i') ?? '—' }}
        <span class="text-slate-500"> · </span>
        {{ $credit->updated_at?->format('Y-m-d H:i') ?? '—' }}
      </div>
    </div>
  </div>
</div>

{{-- ======= Pagos ======= --}}
<div class="bg-slate-950 rounded-xl shadow-lg border border-slate-800">
  <div class="p-4 border-b border-slate-800 flex justify-between items-center">
    <h2 class="text-lg font-bold text-slate-100">💵 Pagos</h2>
    @if($credit->payments->count())
      <div class="text-slate-200 text-sm">
        Total pagado: <span class="font-semibold">{{ $fmt($pagado) }}</span>
      </div>
    @endif
  </div>

  <div class="overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead class="bg-slate-800/80 text-slate-200 uppercase text-xs tracking-wide">
        <tr>
          <th class="px-4 py-3 text-left">Cuota</th>
          <th class="px-4 py-3 text-left">Fecha</th>
          <th class="px-4 py-3 text-right">Monto</th>
          <th class="px-4 py-3 text-left">Método</th>
          <th class="px-4 py-3 text-left">Referencia</th>
          <th class="px-4 py-3 text-left">Nota</th>
          <th class="px-4 py-3 text-left">Usuario</th>
          <th class="px-4 py-3 text-right">Acumulado</th>
          <th class="px-4 py-3 text-right">Saldo después</th>
        </tr>
      </thead>

      @php $acum = 0; @endphp
      <tbody class="divide-y divide-slate-800 text-slate-100">
        @forelse($credit->payments as $p)
          @php
            $acum += (int)$p->amount;
            $saldoDespues = max(0, (int)$credit->amount - $acum);
          @endphp
          <tr class="hover:bg-slate-800/40 transition">
            <td class="px-4 py-3 font-mono text-slate-200">#{{ $loop->iteration }}</td>
            <td class="px-4 py-3 text-slate-200">
              {{ $p->payment_date->format('Y-m-d') }}
              <span class="text-slate-400">·</span>
              {{ $p->created_at?->format('H:i') }}
            </td>
            <td class="px-4 py-3 text-right text-emerald-300">{{ $fmt($p->amount) }}</td>
            <td class="px-4 py-3 text-slate-200">{{ $p->method ?? '—' }}</td>
            <td class="px-4 py-3 text-slate-300">{{ $p->reference ?? '—' }}</td>
            <td class="px-4 py-3 text-slate-300">{{ $p->note ?? '—' }}</td>
            <td class="px-4 py-3 text-slate-300">{{ $p->user->name ?? '—' }}</td>
            <td class="px-4 py-3 text-right text-slate-200">{{ $fmt($acum) }}</td>
            <td class="px-4 py-3 text-right {{ $saldoDespues === 0 ? 'text-emerald-300' : 'text-amber-300' }}">
              {{ $fmt($saldoDespues) }}
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="9" class="px-6 py-6 text-center text-slate-400 italic">Sin pagos registrados</td>
          </tr>
        @endforelse
      </tbody>

      @if($credit->payments->count())
      <tfoot class="bg-slate-900/60 text-slate-100">
        <tr>
          <th class="px-4 py-3 text-right" colspan="2">Total pagado:</th>
          <th class="px-4 py-3 text-right">{{ $fmt($pagado) }}</th>
          <th class="px-4 py-3 text-right" colspan="4">Saldo final:</th>
          <th class="px-4 py-3 text-right" colspan="2">{{ $fmt($credit->balance) }}</th>
        </tr>
      </tfoot>
      @endif
    </table>
  </div>
</div>

{{-- ======= Registrar Pago (solo si no está pagado) ======= --}}
@if($credit->status !== 'pagado')
  @php
    $input = 'w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-base
              text-slate-100 placeholder-slate-300 caret-emerald-400
              focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500
              selection:bg-emerald-500/30';
  @endphp

  <div class="mt-6 bg-slate-900 rounded-xl p-5 shadow-lg border border-slate-700">
    <h2 class="text-lg font-bold text-slate-100 mb-4">➕ Registrar Pago</h2>

    <form action="{{ route('payments.store') }}" method="POST" class="grid grid-cols-1 lg:grid-cols-5 gap-4">
      @csrf
      <input type="hidden" name="credit_id" value="{{ $credit->id }}">

      <div class="lg:col-span-1">
        <label class="block text-xs uppercase tracking-wide text-slate-400 mb-1">
          Monto (Gs.)
        </label>
        <input type="text"
               name="amount"
               inputmode="numeric"
               placeholder="600.000.000"
               class="{{ $input }} amount-input"
               value="{{ old('amount') }}">
        <p class="text-[11px] text-slate-400 mt-1">
          Escribí solo números y el sistema formatea así: <span class="font-semibold">600.000.000</span>
        </p>
        @error('amount') <p class="text-xs text-rose-400 mt-1">{{ $message }}</p> @enderror
      </div>

      <div class="lg:col-span-1">
        <label class="block text-xs uppercase tracking-wide text-slate-400 mb-1">Fecha de pago</label>
        <input type="date" name="payment_date" required class="{{ $input }}" value="{{ old('payment_date') }}">
        @error('payment_date') <p class="text-xs text-rose-400 mt-1">{{ $message }}</p> @enderror
      </div>

      <div class="lg:col-span-1">
        <label class="block text-xs uppercase tracking-wide text-slate-400 mb-1">Método</label>
        <input type="text" name="method" placeholder="Efectivo, Transferencia…" class="{{ $input }}" value="{{ old('method') }}">
        @error('method') <p class="text-xs text-rose-400 mt-1">{{ $message }}</p> @enderror
      </div>

      <div class="lg:col-span-1">
        <label class="block text-xs uppercase tracking-wide text-slate-400 mb-1">Referencia</label>
        <input type="text" name="reference" placeholder="Comprobante / N° op." class="{{ $input }}" value="{{ old('reference') }}">
      </div>

      <div class="lg:col-span-1">
        <label class="block text-xs uppercase tracking-wide text-slate-400 mb-1">Nota</label>
        <input type="text" name="note" placeholder="Observaciones" class="{{ $input }}" value="{{ old('note') }}">
      </div>

      <div class="lg:col-span-5 flex items-center justify-end gap-3 pt-2">
        <a href="{{ route('credits.index') }}"
           class="px-4 py-2 rounded-lg border border-slate-600 text-slate-100 bg-slate-800 hover:bg-slate-700 transition">
          ← Volver
        </a>
        <button type="submit"
                class="px-4 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-500 ring-emerald-500/30 hover:ring-2 transition">
          💵 Registrar Pago
        </button>
      </div>
    </form>
  </div>
@endif
@endsection

@push('styles')
<style>
/* icono del calendario visible en dark */
input[type="date"]::-webkit-calendar-picker-indicator{ filter: invert(1) opacity(.85); }
/* number spinners */
input[type="number"]::-webkit-outer-spin-button,
input[type="number"]::-webkit-inner-spin-button{ -webkit-appearance:none; margin:0; }
input[type="number"]{ -moz-appearance:textfield; }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener("DOMContentLoaded", () => {
    const input = document.querySelector(".amount-input");
    if (!input) return;

    const formatNumber = (value) => {
        value = value.replace(/\D/g, "");
        if (!value) return "";
        return new Intl.NumberFormat("es-PY").format(value);
    };

    const cleanNumber = (value) => value.replace(/\./g, "");

    if (input.value) {
        input.value = formatNumber(cleanNumber(input.value));
    }

    input.addEventListener("input", (e) => {
        const raw = cleanNumber(e.target.value);
        e.target.value = formatNumber(raw);
        e.target.setSelectionRange(e.target.value.length, e.target.value.length);
    });

    input.form?.addEventListener("submit", () => {
        input.value = cleanNumber(input.value);
    });
});
</script>
@endpush
