@extends('layout.admin')

@section('content')
<div class="w-full px-6 py-6 space-y-8 text-gray-100">

  {{-- ================= HEADER ================= --}}
  <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
    <div>
      <h1 class="text-2xl font-bold text-sky-300 flex items-center gap-2">
        📑 Cuenta por pagar #{{ $payable->id }}
      </h1>

      <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
        @php
          $st = $payable->status;
          $statusChip = match ($st) {
            'pendiente' => 'bg-amber-900/40 border-amber-500/60 text-amber-200',
            'parcial'   => 'bg-sky-900/40 border-sky-500/60 text-sky-200',
            'pagado'    => 'bg-emerald-900/40 border-emerald-500/60 text-emerald-200',
            default     => 'bg-gray-800 border-gray-500/60 text-gray-200',
          };
        @endphp

        <span class="px-2 py-0.5 rounded-full border {{ $statusChip }}">
          {{ strtoupper($st ?? '—') }}
        </span>

        @if(($payable->pending_amount ?? 0) > 0)
          <span class="text-amber-200">
            Pendiente: <b class="font-mono">{{ number_format((float)$payable->pending_amount, 0, ',', '.') }}</b> Gs.
          </span>
        @else
          <span class="text-emerald-200">
            ✅ Sin saldo pendiente
          </span>
        @endif
      </div>
    </div>

    <a href="{{ route('payables.index') }}"
       class="inline-flex items-center gap-2 px-4 py-2 rounded-lg
              bg-gray-900 hover:bg-gray-800 border border-gray-700
              text-sm text-gray-100 transition">
      ← Volver al listado
    </a>
  </div>

  {{-- ================= ALERTA VENCIDO ================= --}}
  @if($payable->due_date && ($payable->pending_amount ?? 0) > 0 && now()->gt($payable->due_date))
    <div class="bg-red-950/50 border border-red-600/60 text-red-200 rounded-lg p-4 text-sm">
      ⚠️ Esta cuenta está <strong>vencida</strong> desde
      <span class="font-semibold">
        {{ \Illuminate\Support\Carbon::parse($payable->due_date)->format('d/m/Y') }}
      </span>.
    </div>
  @endif

  {{-- ================= RESUMEN ================= --}}
  <div class="grid md:grid-cols-4 gap-4">

    {{-- Proveedor --}}
    <div class="bg-gray-950 border border-gray-800 rounded-xl p-4 md:col-span-2">
      <h2 class="text-xs uppercase tracking-wide text-gray-400 mb-2">🏢 Proveedor</h2>

      <div class="text-lg text-gray-50 font-semibold">
        {{ $payable->supplier->name ?? '—' }}
      </div>

      <div class="text-xs text-gray-400 mt-1">
        RUC: <span class="font-mono text-gray-200">{{ $payable->supplier->ruc ?? '—' }}</span>
      </div>

      @if($payable->supplier?->phone)
        <div class="text-xs text-gray-400 mt-1">
          Tel.: <span class="text-gray-200">{{ $payable->supplier->phone }}</span>
        </div>
      @endif
    </div>

    {{-- Factura / Recepción --}}
    <div class="bg-gray-950 border border-gray-800 rounded-xl p-4">
      <h2 class="text-xs uppercase tracking-wide text-gray-400 mb-2">🧾 Factura / Recepción</h2>

      @if($payable->invoice)
        <div class="text-gray-100">
          Factura:
          <span class="font-mono text-sky-200">
            {{ $payable->invoice->invoice_number ?? '—' }}
          </span>
        </div>

        <div class="text-xs text-gray-400 mt-1">
          Fecha:
          <span class="text-gray-200">
            {{ $payable->invoice->invoice_date ? \Illuminate\Support\Carbon::parse($payable->invoice->invoice_date)->format('d/m/Y') : '—' }}
          </span>
        </div>
      @else
        <div class="text-sm text-gray-400">—</div>
      @endif

      @if(optional($payable->invoice)->receipt)
        <div class="text-xs text-sky-300 mt-3">
          Recepción:
          <span class="font-mono">{{ $payable->invoice->receipt->receipt_number ?? '—' }}</span>
        </div>

        <div class="text-[11px] text-gray-400 mt-1">
          OC:
          <span class="font-mono text-gray-200">
            {{ $payable->invoice->receipt->order?->order_number ?? '—' }}
          </span>
        </div>
      @endif
    </div>

    {{-- Condición --}}
    <div class="bg-gray-950 border border-gray-800 rounded-xl p-4">
      <h2 class="text-xs uppercase tracking-wide text-gray-400 mb-2">⏳ Condición</h2>

      <div class="text-sm text-gray-200">
        {{ $payable->payment_term ? ucfirst($payable->payment_term) : '—' }}
      </div>

      <div class="text-xs text-gray-400 mt-2">
        Vence:
        <span class="text-gray-200">
          {{ $payable->due_date ? \Illuminate\Support\Carbon::parse($payable->due_date)->format('d/m/Y') : '—' }}
        </span>
      </div>
    </div>

    {{-- Montos --}}
    <div class="bg-gray-950 border border-gray-800 rounded-xl p-4 md:col-span-4">
      <h2 class="text-xs uppercase tracking-wide text-gray-400 mb-3">💰 Montos</h2>

      <div class="grid md:grid-cols-3 gap-3">
        <div class="bg-gray-900/40 border border-gray-800 rounded-lg p-3">
          <div class="text-xs text-gray-400">Total</div>
          <div class="text-lg font-semibold text-gray-100 font-mono">
            {{ number_format((float)$payable->total_amount, 0, ',', '.') }} Gs.
          </div>
        </div>

        <div class="bg-emerald-900/15 border border-emerald-700/40 rounded-lg p-3">
          <div class="text-xs text-emerald-200/90">Pagado</div>
          <div class="text-lg font-semibold text-emerald-200 font-mono">
            {{ number_format((float)$payable->paid_amount, 0, ',', '.') }} Gs.
          </div>
        </div>

        @php $pending = (float)($payable->pending_amount ?? 0); @endphp
        <div class="{{ $pending > 0 ? 'bg-amber-900/15 border-amber-700/40' : 'bg-gray-900/40 border-gray-800' }} border rounded-lg p-3">
          <div class="text-xs {{ $pending > 0 ? 'text-amber-200/90' : 'text-gray-400' }}">Pendiente</div>
          <div class="text-lg font-semibold {{ $pending > 0 ? 'text-amber-200' : 'text-gray-200' }} font-mono">
            {{ number_format($pending, 0, ',', '.') }} Gs.
          </div>
        </div>
      </div>
    </div>

  </div>

  {{-- ================= HISTORIAL ================= --}}
  <div class="bg-gray-950 border border-gray-800 rounded-xl p-4">
    <div class="flex items-center justify-between gap-3 mb-3">
      <h2 class="text-lg font-semibold text-gray-50">📜 Historial de pagos</h2>
      <span class="text-xs text-gray-400">
        Registros: <b class="text-gray-200">{{ $payable->payments?->count() ?? 0 }}</b>
      </span>
    </div>

    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-gray-900 text-gray-200 text-xs uppercase tracking-wide">
          <tr>
            <th class="px-3 py-2 text-left whitespace-nowrap">Fecha de pago</th>
            <th class="px-3 py-2 text-right whitespace-nowrap">Monto</th>
            <th class="px-3 py-2 text-left whitespace-nowrap">Método</th>
            <th class="px-3 py-2 text-left whitespace-nowrap">Referencia</th>
            <th class="px-3 py-2 text-left whitespace-nowrap">Registrado por</th>
            <th class="px-3 py-2 text-left whitespace-nowrap">Registrado en</th>
          </tr>
        </thead>

        <tbody class="divide-y divide-gray-800">
          @forelse(($payable->payments ?? collect()) as $pay)
            @php
              $payDate = $pay->payment_date
                ? \Illuminate\Support\Carbon::parse($pay->payment_date)->format('d/m/Y')
                : '—';

              $method = $pay->method ? ucfirst($pay->method) : '—';
              $ref    = $pay->reference ?: '—';
              $by     = optional($pay->user)->name ?: ('Usuario #'.($pay->created_by ?? '—'));
              $regAt  = $pay->created_at ? $pay->created_at->format('d/m/Y H:i') : '—';

              $methodBadge = match (strtolower((string)$pay->method)) {
                'efectivo'       => 'bg-emerald-900/30 border-emerald-700/40 text-emerald-200',
                'transferencia'  => 'bg-sky-900/30 border-sky-700/40 text-sky-200',
                'deposito'       => 'bg-amber-900/30 border-amber-700/40 text-amber-200',
                'cheque'         => 'bg-purple-900/30 border-purple-700/40 text-purple-200',
                'pix'            => 'bg-fuchsia-900/30 border-fuchsia-700/40 text-fuchsia-200',
                default          => 'bg-gray-900/30 border-gray-700/40 text-gray-200',
              };
            @endphp

            <tr class="hover:bg-gray-900/50 transition">
              <td class="px-3 py-2 whitespace-nowrap text-gray-100">{{ $payDate }}</td>

              <td class="px-3 py-2 text-right whitespace-nowrap">
                <span class="text-emerald-200 font-mono font-semibold">
                  {{ number_format((float)$pay->amount, 0, ',', '.') }} Gs.
                </span>
              </td>

              <td class="px-3 py-2 whitespace-nowrap">
                <span class="inline-flex items-center px-2 py-0.5 rounded-full border text-xs {{ $methodBadge }}">
                  {{ $method }}
                </span>
              </td>

              <td class="px-3 py-2 text-xs text-gray-300 whitespace-nowrap">
                <span class="font-mono">{{ $ref }}</span>
              </td>

              <td class="px-3 py-2 text-xs text-gray-100 whitespace-nowrap">
                {{ $by }}
              </td>

              <td class="px-3 py-2 text-xs text-gray-400 whitespace-nowrap">
                {{ $regAt }}
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="6" class="px-3 py-6 text-center text-gray-400">
                Todavía no hay pagos registrados.
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{-- ================= FORMULARIO DE PAGO ================= --}}
  @if(($payable->pending_amount ?? 0) > 0)
    <div class="bg-gray-950 border border-gray-800 rounded-xl p-4">
      <h2 class="text-lg font-semibold text-gray-50 mb-1">💾 Registrar nuevo pago</h2>
      <p class="text-xs text-gray-400 mb-4">
        Registrá el pago y el sistema actualizará el saldo pendiente.
      </p>

      <form method="POST"
            action="{{ route('payables.payments.store', $payable) }}"
            id="paymentForm"
            class="grid md:grid-cols-4 gap-4">
        @csrf

        <div>
          <label class="block text-xs text-gray-400 mb-1">Fecha de pago</label>
          <input type="date" name="payment_date"
                 value="{{ now()->format('Y-m-d') }}"
                 class="w-full rounded-lg bg-gray-900 border border-gray-700 text-gray-100 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-sky-600/30"
                 required>
        </div>

        <div>
          <label class="block text-xs text-gray-400 mb-1">Monto (Gs.)</label>

          {{-- Visible (formateado) --}}
          <input type="text" id="amount_visible"
                 class="w-full rounded-lg bg-gray-900 border border-gray-700 text-gray-100 px-3 py-2 font-mono
                        focus:outline-none focus:ring-2 focus:ring-emerald-600/30"
                 placeholder="16.500" autocomplete="off" inputmode="numeric" required>

          {{-- Raw (solo dígitos) --}}
          <input type="hidden" id="amount_raw" name="amount" value="">
          <p class="text-[11px] text-gray-500 mt-1">Se guardará en la BD sin puntos.</p>
        </div>

        <div>
          <label class="block text-xs text-gray-400 mb-1">Método</label>
          <select name="method"
                  class="w-full rounded-lg bg-gray-900 border border-gray-700 text-gray-100 px-3 py-2
                         focus:outline-none focus:ring-2 focus:ring-sky-600/30"
                  required>
            <option value="">Seleccione…</option>
            <option value="efectivo">Efectivo</option>
            <option value="transferencia" selected>Transferencia</option>
            <option value="deposito">Depósito</option>
            <option value="cheque">Cheque</option>
            <option value="pix">PIX</option>
            <option value="otro">Otro</option>
          </select>
        </div>

        <div>
          <label class="block text-xs text-gray-400 mb-1">Referencia</label>
          <input type="text" name="reference"
                 class="w-full rounded-lg bg-gray-900 border border-gray-700 text-gray-100 px-3 py-2
                        focus:outline-none focus:ring-2 focus:ring-sky-600/30"
                 placeholder="Ej: 9539">
        </div>

        <div class="md:col-span-4 flex justify-end">
          <button class="px-5 py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-semibold shadow">
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
  const visible = document.getElementById("amount_visible");
  const rawInp  = document.getElementById("amount_raw");
  if (!visible || !rawInp) return;

  const onlyDigits = (v) => (v || "").toString().replace(/\D+/g, "");
  const formatPYG  = (digits) => digits ? digits.replace(/\B(?=(\d{3})+(?!\d))/g, ".") : "";

  // Init
  const init = onlyDigits(visible.value);
  rawInp.value = init;
  visible.value = formatPYG(init);

  visible.addEventListener("input", () => {
    const raw = onlyDigits(visible.value);
    rawInp.value = raw;              // ✅ lo que se envía
    visible.value = formatPYG(raw);  // ✅ lo que se ve
    visible.setSelectionRange(visible.value.length, visible.value.length);
  });

  visible.form?.addEventListener("submit", () => {
    rawInp.value = onlyDigits(visible.value);
  });
});
</script>

@endsection
