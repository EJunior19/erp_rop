@extends('layout.admin')

@section('content')
<div class="w-full px-6 py-6 space-y-8">

  {{-- ================= HEADER ================= --}}
  <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
    <div>
      <h1 class="text-2xl font-bold text-sky-400 flex items-center gap-2">
        📑 Cuenta por pagar #{{ $payable->id }}
      </h1>

      <div class="mt-1 flex flex-wrap items-center gap-2 text-xs">
        <span class="px-2 py-0.5 rounded-full border
          @switch($payable->status)
            @case('pendiente') bg-amber-900/40 border-amber-500 text-amber-200 @break
            @case('parcial')   bg-sky-900/40 border-sky-500 text-sky-200 @break
            @case('pagado')    bg-emerald-900/40 border-emerald-500 text-emerald-200 @break
            @default bg-gray-800 border-gray-500 text-gray-200
          @endswitch">
          {{ strtoupper($payable->status) }}
        </span>

        @if($payable->pending_amount > 0)
          <span class="text-amber-300">
            Pendiente: {{ number_format($payable->pending_amount, 0, ',', '.') }} Gs.
          </span>
        @endif
      </div>
    </div>

    <a href="{{ route('payables.index') }}"
       class="inline-flex items-center gap-2 px-4 py-2 rounded-lg
              bg-gray-800 hover:bg-gray-700 border border-gray-600
              text-sm text-gray-100 transition">
      ← Volver al listado
    </a>
  </div>

  {{-- ================= ALERTA VENCIDO ================= --}}
  @if($payable->due_date && $payable->pending_amount > 0 && now()->gt($payable->due_date))
    <div class="bg-red-900/30 border border-red-600 text-red-200 rounded-lg p-3 text-sm">
      ⚠️ Esta cuenta está <strong>vencida</strong> desde
      {{ \Illuminate\Support\Carbon::parse($payable->due_date)->format('d/m/Y') }}.
    </div>
  @endif

  {{-- ================= RESUMEN ================= --}}
  <div class="grid md:grid-cols-4 gap-4">

    {{-- Proveedor --}}
    <div class="bg-gray-900 border border-gray-700 rounded-xl p-4 md:col-span-2">
      <h2 class="text-sm text-gray-400 mb-1">🏢 Proveedor</h2>
      <div class="text-lg text-gray-100 font-semibold">
        {{ $payable->supplier->name ?? '—' }}
      </div>
      <div class="text-xs text-gray-400">RUC: {{ $payable->supplier->ruc ?? '—' }}</div>
      @if($payable->supplier?->phone)
        <div class="text-xs text-gray-400 mt-1">Tel.: {{ $payable->supplier->phone }}</div>
      @endif
    </div>

    {{-- Factura / Recepción --}}
    <div class="bg-gray-900 border border-gray-700 rounded-xl p-4">
      <h2 class="text-sm text-gray-400 mb-1">🧾 Factura / Recepción</h2>

      @if($payable->invoice)
        <div class="text-gray-100">
          Factura: <span class="font-mono">{{ $payable->invoice->invoice_number }}</span>
        </div>
        <div class="text-xs text-gray-400">
          Fecha: {{ \Illuminate\Support\Carbon::parse($payable->invoice->invoice_date)->format('d/m/Y') }}
        </div>
      @endif

      @if(optional($payable->invoice)->receipt)
        <div class="text-xs text-sky-300 mt-1">
          Recepción: {{ $payable->invoice->receipt->receipt_number }}
        </div>
        <div class="text-[11px] text-gray-400">
          OC: {{ $payable->invoice->receipt->order?->order_number ?? '—' }}
        </div>
      @endif
    </div>

    {{-- Condición --}}
    <div class="bg-gray-900 border border-gray-700 rounded-xl p-4">
      <h2 class="text-sm text-gray-400 mb-1">⏳ Condición</h2>
      <div class="text-xs text-gray-300">
        {{ ucfirst($payable->payment_term ?? '—') }}
      </div>
      <div class="text-xs text-gray-300 mt-1">
        Vence:
        {{ $payable->due_date ? \Illuminate\Support\Carbon::parse($payable->due_date)->format('d/m/Y') : '—' }}
      </div>
    </div>

    {{-- Montos --}}
    <div class="bg-gray-900 border border-gray-700 rounded-xl p-4">
      <h2 class="text-sm text-gray-400 mb-1">💰 Montos</h2>

      <div class="flex justify-between text-xs text-gray-200">
        <span>Total</span>
        <span class="font-mono">{{ number_format($payable->total_amount, 0, ',', '.') }} Gs.</span>
      </div>

      <div class="flex justify-between text-xs text-emerald-200">
        <span>Pagado</span>
        <span class="font-mono">{{ number_format($payable->paid_amount, 0, ',', '.') }} Gs.</span>
      </div>

      <div class="flex justify-between text-xs {{ $payable->pending_amount > 0 ? 'text-amber-200' : 'text-gray-300' }}">
        <span>Pendiente</span>
        <span class="font-mono">{{ number_format($payable->pending_amount, 0, ',', '.') }} Gs.</span>
      </div>
    </div>
  </div>

  {{-- ================= HISTORIAL ================= --}}
  <div class="bg-gray-900 border border-gray-700 rounded-xl p-4">
    <h2 class="text-lg font-semibold text-gray-100 mb-3">📜 Historial de pagos</h2>

    <table class="w-full text-sm">
      <thead class="bg-gray-800 text-gray-300 text-xs uppercase">
        <tr>
          <th class="px-3 py-2 text-left">Fecha</th>
          <th class="px-3 py-2 text-right">Monto</th>
          <th class="px-3 py-2 text-left">Método</th>
          <th class="px-3 py-2 text-left">Referencia</th>
          <th class="px-3 py-2 text-left">Registrado por</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-700">
        @forelse($payable->payments as $pay)
          <tr>
            <td class="px-3 py-2">{{ optional($pay->payment_date)->format('d/m/Y') }}</td>
            <td class="px-3 py-2 text-right text-emerald-200 font-mono">
              {{ number_format($pay->amount, 0, ',', '.') }} Gs.
            </td>
            <td class="px-3 py-2">{{ ucfirst($pay->method) }}</td>
            <td class="px-3 py-2 text-xs text-gray-300">{{ $pay->reference ?? '—' }}</td>
            <td class="px-3 py-2 text-xs text-gray-400">{{ $pay->user->name ?? '—' }}</td>
          </tr>
        @empty
          <tr>
            <td colspan="5" class="px-3 py-4 text-center text-gray-400">
              Todavía no hay pagos registrados.
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  {{-- ================= FORMULARIO DE PAGO ================= --}}
  @if($payable->pending_amount > 0)
  <div class="bg-gray-900 border border-gray-700 rounded-xl p-4">
    <h2 class="text-lg font-semibold text-gray-100 mb-1">💾 Registrar nuevo pago</h2>

    <form method="POST"
          action="{{ route('payables.payments.store', $payable) }}"
          id="paymentForm"
          class="grid md:grid-cols-4 gap-4">
      @csrf

      <div>
        <label class="block text-xs text-gray-400 mb-1">Fecha de pago</label>
        <input type="date" name="payment_date"
               value="{{ now()->format('Y-m-d') }}"
               class="w-full rounded-lg bg-gray-800 border border-gray-700 text-gray-100 px-3 py-2" required>
      </div>

      <div>
        <label class="block text-xs text-gray-400 mb-1">Monto (Gs.)</label>
        <input type="text" name="amount"
               class="amount-input w-full rounded-lg bg-gray-800 border border-gray-700 text-gray-100 px-3 py-2"
               placeholder="16.500" required>
      </div>

      <div>
        <label class="block text-xs text-gray-400 mb-1">Método</label>
        <select name="method"
                class="w-full rounded-lg bg-gray-800 border border-gray-700 text-gray-100 px-3 py-2" required>
          <option value="">Seleccione…</option>
          <option value="efectivo">Efectivo</option>
          <option value="transferencia">Transferencia</option>
          <option value="deposito">Depósito</option>
          <option value="cheque">Cheque</option>
          <option value="pix">PIX</option>
          <option value="otro">Otro</option>
        </select>
      </div>

      <div>
        <label class="block text-xs text-gray-400 mb-1">Referencia</label>
        <input type="text" name="reference"
               class="w-full rounded-lg bg-gray-800 border border-gray-700 text-gray-100 px-3 py-2">
      </div>

      <div class="md:col-span-4 flex justify-end">
        <button class="px-5 py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-semibold">
          Registrar pago
        </button>
      </div>
    </form>
  </div>
  @endif

</div>

{{-- ================= JS FORMATO MONEDA ================= --}}
<script>
document.addEventListener("DOMContentLoaded", () => {
  const input = document.querySelector(".amount-input");
  if (!input) return;

  const format = v => new Intl.NumberFormat("es-PY").format(v);
  const clean  = v => v.replace(/\./g, "").replace(/\D/g, "");

  input.addEventListener("input", e => {
    const raw = clean(e.target.value);
    e.target.value = raw ? format(raw) : "";
  });

  document.getElementById("paymentForm").addEventListener("submit", () => {
    input.value = clean(input.value);
  });
});
</script>
@endsection
