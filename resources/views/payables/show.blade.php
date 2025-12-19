@extends('layout.admin')

@section('content')
<div class="w-full px-6 py-6 space-y-6">
  {{-- HEADER --}}
  <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
    <h1 class="text-2xl font-bold text-sky-400 flex items-center gap-2">
      📑 Cuenta por pagar #{{ $payable->id }}
    </h1>

    <div class="flex gap-2 justify-start md:justify-end">
      {{-- Botón volver al index --}}
      <a href="{{ route('payables.index') }}"
         class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-gray-800 hover:bg-gray-700 border border-gray-600 text-sm text-gray-100 shadow-sm transition">
        <span class="text-lg">←</span>
        <span>Volver al listado</span>
      </a>
    </div>
  </div>

  {{-- RESUMEN --}}
  <div class="grid md:grid-cols-4 gap-4">

    {{-- Proveedor --}}
    <div class="bg-gray-900 border border-gray-700 rounded-xl p-4 md:col-span-2">
      <h2 class="text-sm text-gray-400 mb-1">Proveedor</h2>
      <div class="text-lg text-gray-100 font-semibold">
        {{ $payable->supplier->name ?? '—' }}
      </div>
      <div class="text-xs text-gray-400">
        RUC: {{ $payable->supplier->ruc ?? '—' }}
      </div>
      @if($payable->supplier?->phone)
        <div class="text-xs text-gray-400 mt-1">
          Tel.: {{ $payable->supplier->phone }}
        </div>
      @endif
    </div>

    {{-- Factura / Recepción --}}
    <div class="bg-gray-900 border border-gray-700 rounded-xl p-4">
      <h2 class="text-sm text-gray-400 mb-1">Factura / Recepción</h2>

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

    {{-- Condición / Estado --}}
    <div class="bg-gray-900 border border-gray-700 rounded-xl p-4">
      <h2 class="text-sm text-gray-400 mb-1">Condición / Estado</h2>

      <div class="text-xs text-gray-300">
        Condición:
        <span class="font-semibold">
          {{ ucfirst($payable->payment_term ?? '—') }}
        </span>
      </div>

      <div class="text-xs text-gray-300 mt-1">
        Vence:
        @if($payable->due_date)
          {{ \Illuminate\Support\Carbon::parse($payable->due_date)->format('d/m/Y') }}
        @else
          —
        @endif
      </div>

      <div class="mt-2 text-xs">
        Estado:
        <span class="px-2 py-0.5 rounded-full border text-[11px]
          @switch($payable->status)
            @case('pendiente') bg-amber-900/40 border-amber-500/60 text-amber-200 @break
            @case('parcial')   bg-sky-900/40   border-sky-500/60   text-sky-200   @break
            @case('pagado')    bg-emerald-900/40 border-emerald-500/60 text-emerald-200 @break
            @default           bg-gray-800 border-gray-500 text-gray-200
          @endswitch">
          {{ ucfirst($payable->status ?? '—') }}
        </span>
      </div>
    </div>

    {{-- Montos --}}
    <div class="bg-gray-900 border border-gray-700 rounded-xl p-4">
      <h2 class="text-sm text-gray-400 mb-1">Montos</h2>

      <div class="flex justify-between text-gray-200 text-xs mb-1">
        <span>Total</span>
        <span>{{ number_format($payable->total_amount, 0, ',', '.') }} Gs.</span>
      </div>

      <div class="flex justify-between text-emerald-200 text-xs mb-1">
        <span>Pagado</span>
        <span>{{ number_format($payable->paid_amount, 0, ',', '.') }} Gs.</span>
      </div>

      <div class="flex justify-between text-xs {{ $payable->pending_amount > 0 ? 'text-amber-200' : 'text-gray-300' }}">
        <span>Pendiente</span>
        <span>{{ number_format($payable->pending_amount, 0, ',', '.') }} Gs.</span>
      </div>
    </div>
  </div>

  {{-- HISTORIAL DE PAGOS --}}
  <div class="bg-gray-900 border border-gray-700 rounded-xl p-4">
    <h2 class="text-lg font-semibold text-gray-100 mb-3">Historial de pagos</h2>

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
            <td class="px-3 py-2">
              {{ optional($pay->payment_date)->format('d/m/Y') }}
            </td>
            <td class="px-3 py-2 text-right text-emerald-200">
              {{ number_format($pay->amount, 0, ',', '.') }} Gs.
            </td>
            <td class="px-3 py-2">{{ $pay->method ?? '—' }}</td>
            <td class="px-3 py-2 text-xs text-gray-300">{{ $pay->reference ?? '—' }}</td>
            <td class="px-3 py-2 text-xs text-gray-400">
              {{ $pay->user->name ?? '—' }}
            </td>
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

  {{-- FORMULARIO: REGISTRAR NUEVO PAGO --}}
  @if($payable->pending_amount > 0)
    <div class="bg-gray-900 border border-gray-700 rounded-xl p-4">
      <div class="flex items-center justify-between mb-3">
        <h2 class="text-lg font-semibold text-gray-100">Registrar nuevo pago</h2>

        {{-- Botón volver secundario (opcional, cerca del formulario) --}}
        <a href="{{ route('payables.index') }}"
           class="hidden md:inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-gray-800 hover:bg-gray-700 border border-gray-600 text-xs text-gray-100 transition">
          ← Volver al listado
        </a>
      </div>

      @if ($errors->any())
        <div class="mb-3 p-3 bg-red-900/40 border border-red-600 text-red-200 rounded">
          <ul class="list-disc ml-4 text-sm">
            @foreach ($errors->all() as $e)
              <li>{{ $e }}</li>
            @endforeach
          </ul>
        </div>
      @endif

      <form method="POST"
            action="{{ route('payables.payments.store', $payable) }}"
            class="grid md:grid-cols-4 gap-4">
        @csrf

        <div>
          <label class="block text-xs text-gray-400 mb-1">Fecha de pago</label>
          <input type="date"
                 name="payment_date"
                 value="{{ old('payment_date', now()->format('Y-m-d')) }}"
                 class="w-full rounded-lg bg-gray-800 border border-gray-700 text-gray-100 px-3 py-2"
                 required>
        </div>

        <div>
          <label class="block text-xs text-gray-400 mb-1">Monto (Gs.)</label>
          <input type="text"
                 name="amount"
                 value="{{ old('amount') }}"
                 class="amount-input w-full rounded-lg bg-gray-800 border border-gray-700 text-gray-100 px-3 py-2"
                 required>
          <p class="text-xs text-gray-500 mt-1">
            Pendiente: {{ number_format($payable->pending_amount, 0, ',', '.') }} Gs.
          </p>
        </div>

        <div>
          <label class="block text-xs text-gray-400 mb-1">Método</label>
          <input type="text"
                 name="method"
                 value="{{ old('method') }}"
                 placeholder="Efectivo, transferencia, cheque…"
                 class="w-full rounded-lg bg-gray-800 border border-gray-700 text-gray-100 px-3 py-2">
        </div>

        <div>
          <label class="block text-xs text-gray-400 mb-1">Referencia</label>
          <input type="text"
                 name="reference"
                 value="{{ old('reference') }}"
                 placeholder="N° comprobante, N° transf…"
                 class="w-full rounded-lg bg-gray-800 border border-gray-700 text-gray-100 px-3 py-2">
        </div>

        <div class="md:col-span-4">
          <label class="block text-xs text-gray-400 mb-1">Notas</label>
          <textarea name="notes"
                    rows="2"
                    class="w-full rounded-lg bg-gray-800 border border-gray-700 text-gray-100 px-3 py-2">{{ old('notes') }}</textarea>
        </div>

        <div class="md:col-span-4 flex justify-between items-center mt-2">
          <a href="{{ route('payables.index') }}"
             class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-gray-800 hover:bg-gray-700 border border-gray-600 text-xs text-gray-100 transition md:hidden">
            ← Volver
          </a>

          <div class="flex-1"></div>

          <button class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-semibold">
            Registrar pago
          </button>
        </div>
      </form>

      <script>
        document.addEventListener("DOMContentLoaded", () => {
            const input = document.querySelector(".amount-input");
            if (!input) return;

            const formatNumber = (value) => {
                value = value.replace(/\D/g, ""); // solo números
                if (!value) return "";
                return new Intl.NumberFormat('es-PY').format(value);
            };

            const cleanNumber = (value) => value.replace(/\./g, "");

            // Formatear si viene con old()
            if (input.value) {
                input.value = formatNumber(cleanNumber(input.value));
            }

            // Formatear mientras escribe
            input.addEventListener("input", (e) => {
                const raw = cleanNumber(e.target.value);
                e.target.value = formatNumber(raw);
                e.target.setSelectionRange(e.target.value.length, e.target.value.length);
            });

            // Antes de enviar → limpiar puntos
            input.form?.addEventListener("submit", () => {
                input.value = cleanNumber(input.value);
            });
        });
      </script>
    </div>
  @endif
</div>
@endsection
