{{-- resources/views/credits/index.blade.php --}}
@extends('layout.admin')

@section('content')
{{-- ========= Encabezado ========= --}}
<div class="mb-5">
  <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-2">
    <div>
      <h1 class="text-2xl md:text-3xl font-bold text-slate-100 flex items-center gap-2">
        💳 Créditos
        <span class="text-[11px] font-normal text-slate-400 align-middle hidden md:inline">
          Gestión de cuentas por cobrar
        </span>
      </h1>
      <p class="text-xs text-slate-400 mt-1">
        Visualizá los créditos activos, vencidos y pagados, con filtros rápidos por estado, vencimiento y cliente.
      </p>
    </div>

    {{-- (opcional) botón crear crédito --}}
    {{-- 
    <x-create-button route="{{ route('credits.create') }}" text="Nuevo crédito" />
    --}}
  </div>
</div>

<x-flash-message />

{{-- ========= Barra de filtros compacta ========= --}}
<form method="GET"
      class="bg-slate-900/90 border border-slate-700/80 rounded-xl p-3 md:p-4 mb-4"
      x-data="{ q: '{{ request('q') }}' }">

  {{-- Primera línea: filtros principales --}}
  <div class="grid grid-cols-1 lg:grid-cols-12 gap-2.5">

    {{-- Buscar --}}
    <div class="lg:col-span-4">
      <label class="block text-[11px] uppercase tracking-wide text-slate-400 mb-1">Buscar</label>
      <div class="relative">
        <input type="text" name="q" x-model="q"
               @input.debounce.400ms="$root.submit()"
               placeholder="Cliente, #crédito o #venta"
               class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 pl-9 py-2 text-sm text-slate-200
                      focus:outline-none focus:ring-2 focus:ring-emerald-500">
        <span class="absolute left-3 top-2.5 text-slate-500 text-xs">🔎</span>
      </div>
    </div>

    {{-- Estado --}}
    <div class="lg:col-span-2">
      <label class="block text-[11px] uppercase tracking-wide text-slate-400 mb-1">Estado</label>
      <select name="status"
              class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-200
                     focus:outline-none focus:ring-2 focus:ring-emerald-500"
              onchange="this.form.submit()">
        <option value="">Todos</option>
        @foreach(['pendiente'=>'Pendiente','pagado'=>'Pagado','vencido'=>'Vencido'] as $k=>$v)
          <option value="{{ $k }}" @selected(request('status')===$k)>{{ $v }}</option>
        @endforeach
      </select>
    </div>

    {{-- Vence desde / hasta --}}
    <div class="lg:col-span-2">
      <label class="block text-[11px] uppercase tracking-wide text-slate-400 mb-1">Vence desde</label>
      <input type="date" name="due_from" value="{{ request('due_from') }}"
             class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-200
                    focus:outline-none focus:ring-2 focus:ring-emerald-500"
             onchange="this.form.submit()">
    </div>

    <div class="lg:col-span-2">
      <label class="block text-[11px] uppercase tracking-wide text-slate-400 mb-1">Vence hasta</label>
      <input type="date" name="due_to" value="{{ request('due_to') }}"
             class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-200
                    focus:outline-none focus:ring-2 focus:ring-emerald-500"
             onchange="this.form.submit()">
    </div>

    {{-- Orden --}}
    <div class="lg:col-span-2">
      <label class="block text-[11px] uppercase tracking-wide text-slate-400 mb-1">Orden</label>
      <select name="order"
              class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-200
                     focus:outline-none focus:ring-2 focus:ring-emerald-500"
              onchange="this.form.submit()">
        <option value="due_asc"   @selected(request('order','due_asc')==='due_asc')>Vencimiento ↑</option>
        <option value="due_desc"  @selected(request('order')==='due_desc')>Vencimiento ↓</option>
        <option value="bal_desc"  @selected(request('order')==='bal_desc')>Saldo ↓</option>
        {{-- 👉 nuevo: vencido → pendiente → pagado --}}
        <option value="status_bal" @selected(request('order')==='status_bal')>
          Estado (vencido → pendiente → pagado)
        </option>
      </select>
    </div>

    {{-- Segunda línea: toggle semana + por página + limpiar --}}
    <div class="lg:col-span-12 flex flex-wrap items-center justify-between gap-2 pt-1">
      <div class="flex flex-wrap items-center gap-2">
        <label class="inline-flex items-center gap-2 text-sm text-slate-300">
          <input type="checkbox" name="this_week" value="1"
                 @checked(request()->boolean('this_week'))
                 onchange="this.form.submit()">
          Solo próximos 7 días
        </label>

        {{-- Chips de filtros activos --}}
        <div class="flex flex-wrap gap-2 text-[11px]">
          @if(request('status'))
            <span class="inline-flex items-center px-2 py-1 rounded-full bg-slate-800 border border-slate-600 text-slate-200">
              Estado: <span class="ml-1 font-semibold">{{ ucfirst(request('status')) }}</span>
            </span>
          @endif
          @if(request('due_from') || request('due_to'))
            <span class="inline-flex items-center px-2 py-1 rounded-full bg-slate-800 border border-slate-600 text-slate-200">
              Vencimiento:
              <span class="ml-1 font-semibold">
                {{ request('due_from') ?: '—' }} → {{ request('due_to') ?: '—' }}
              </span>
            </span>
          @endif
          @if(request()->boolean('this_week'))
            <span class="inline-flex items-center px-2 py-1 rounded-full bg-amber-900/40 border border-amber-500/60 text-amber-100">
              Próximos 7 días
            </span>
          @endif
        </div>
      </div>

      <div class="flex items-center gap-2">
        <select name="per_page"
                class="rounded-lg bg-slate-950 border border-slate-700 px-2 py-2 text-sm text-slate-200
                       focus:outline-none focus:ring-2 focus:ring-emerald-500"
                onchange="this.form.submit()">
          @foreach([10,15,25,50,100] as $n)
            <option value="{{ $n }}" @selected((int)request('per_page',15)===$n)>{{ $n }}/pág</option>
          @endforeach
        </select>
        <a href="{{ route('credits.index') }}"
           class="px-3 py-2 rounded-lg border border-slate-600 text-slate-200 bg-slate-800 hover:bg-slate-700 transition text-sm">
          Limpiar
        </a>
      </div>
    </div>
  </div>
</form>

{{-- ========= Tabla ========= --}}
<div class="bg-slate-900 rounded-xl shadow-md border border-slate-700">
  <div class="overflow-x-auto rounded-t-xl">
    <table class="min-w-full text-sm">
      <thead class="bg-slate-800/95 text-slate-200 uppercase text-[11px] tracking-wider sticky top-0 z-10">
        <tr>
          <th class="px-4 py-3 text-left">#</th>
          <th class="px-4 py-3 text-left">Cliente</th>
          <th class="px-4 py-3 text-left">Venta</th>
          <th class="px-4 py-3 text-right">Monto</th>
          <th class="px-4 py-3 text-right">Saldo</th>
          <th class="px-4 py-3 text-left">Vencimiento</th>
          <th class="px-4 py-3 text-left">Vence en</th>
          <th class="px-4 py-3 text-center">Estado</th>
          <th class="px-4 py-3 text-right w-[260px]">Acciones</th>
        </tr>
      </thead>

      <tbody class="divide-y divide-slate-800 text-slate-100">
        @forelse($credits as $credit)
          @php
            $days = $credit->days_to_due ?? ( $credit->due_date ? now()->startOfDay()->diffInDays($credit->due_date, false) : null );
            $rowClass =
              $credit->status === 'vencido'
                ? 'ring-1 ring-rose-900/40 bg-rose-950/20'
                : ($days !== null && $days >= 0 && $days <= 7
                    ? 'bg-amber-900/10'
                    : '');
            $lastPayment = $credit->payments->first(); // with() en el controlador
          @endphp

          <tr class="hover:bg-slate-800/50 transition {{ $rowClass }}">
            <td class="px-4 py-3 font-mono text-slate-300 align-top">#{{ $credit->id }}</td>

            <td class="px-4 py-3 align-top">
              <div class="flex flex-col">
                <span class="font-medium truncate max-w-xs">
                  {{ $credit->client->name ?? '—' }}
                </span>
                <span class="text-xs text-slate-400">
                  CI/RUC: {{ $credit->client->ruc ?? '—' }}
                </span>
              </div>
            </td>

            <td class="px-4 py-3 align-top">
              #{{ $credit->sale->id ?? '—' }}
            </td>

            <td class="px-4 py-3 text-right align-top">
              <span class="tabular-nums">
                Gs. {{ number_format($credit->amount, 0, ',', '.') }}
              </span>
            </td>

            <td class="px-4 py-3 text-right align-top">
              <span class="tabular-nums font-semibold {{ (int)$credit->balance === 0 ? 'text-emerald-400' : 'text-slate-200' }}">
                Gs. {{ number_format($credit->balance, 0, ',', '.') }}
              </span>
            </td>

            <td class="px-4 py-3 align-top">
              {{ $credit->due_date?->format('Y-m-d') ?? '—' }}
            </td>

            <td class="px-4 py-3 align-top">
              @if(is_null($days))
                <span class="text-slate-400">—</span>
              @elseif($days < 0)
                <span class="text-rose-400 font-medium">
                  hace {{ abs($days) }} día{{ abs($days) == 1 ? '' : 's' }}
                </span>
              @elseif($days === 0)
                <span class="text-amber-300 font-medium">hoy</span>
              @else
                <span class="text-sky-300 font-medium">
                  en {{ $days }} día{{ $days == 1 ? '' : 's' }}
                </span>
              @endif
            </td>

            <td class="px-4 py-3 text-center align-top">
              <x-status-badge 
                :status="$credit->status === 'pagado' ? 'aprobado' 
                        : ($credit->status === 'vencido' ? 'rechazado' 
                        : 'pendiente')" 
                :label="ucfirst($credit->status)" />
            </td>

            {{-- ===== Acciones con componentes ===== --}}
            <td class="px-4 py-3 align-top">
              <div class="flex items-center justify-end gap-2">
                {{-- Ver --}}
                <x-link-button 
                  color="sky" icon="👁️"
                  :href="route('credits.show', $credit)"
                  text="Ver" />

                {{-- Recibo (si hay al menos un pago) --}}
                @if($lastPayment)
                  <x-link-button
                    color="emerald" icon="🧾"
                    :href="route('payments.receipt', $lastPayment)"
                    target="_blank"
                    text="Recibo" />
                @else
                  <x-link-button color="slate" icon="🧾" :disabled="true" text="Recibo" />
                @endif

                {{-- Eliminar con el mismo componente que usás en Ventas --}}
                <x-delete-button 
                  :action="route('credits.destroy',$credit)" 
                  :name="'el crédito #'.$credit->id" />
              </div>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="9" class="px-6 py-10 text-center">
              <div class="text-slate-400">No se encontraron créditos con los filtros actuales.</div>
              <a href="{{ route('credits.index') }}"
                 class="inline-block mt-3 px-3 py-2 rounded-lg border border-slate-600 text-slate-200 bg-slate-800 hover:bg-slate-700 transition text-sm">
                Limpiar filtros
              </a>
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="p-4 border-t border-slate-700 flex flex-col md:flex-row items-center justify-between gap-2 text-xs text-slate-400">
    <div>
      @if($credits->total() > 0)
        Mostrando
        <span class="font-semibold text-slate-200">{{ $credits->firstItem() }}</span>
        a
        <span class="font-semibold text-slate-200">{{ $credits->lastItem() }}</span>
        de
        <span class="font-semibold text-slate-200">{{ $credits->total() }}</span>
        créditos.
      @else
        Sin créditos para mostrar.
      @endif
    </div>
    <div>
      {{ $credits->onEachSide(1)->withQueryString()->links() }}
    </div>
  </div>
</div>
@endsection
