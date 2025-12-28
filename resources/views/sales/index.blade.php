{{-- resources/views/sales/index.blade.php --}}
@extends('layout.admin')

@section('title','Ventas · CRM Katuete')

@push('styles')
<style>
  .tbl-sticky thead{ position:sticky; top:0; z-index:10; }
  .num{ text-align:right; font-variant-numeric: tabular-nums; }
  .mono{
    font-variant-numeric: tabular-nums;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
  }

  /* Badges */
  .badge{
    display:inline-flex; align-items:center; gap:.4rem;
    font-size:.72rem; line-height:1;
    padding:.35rem .6rem; border-radius:.55rem;
    border:1px solid transparent;
    white-space:nowrap;
  }
  .badge-indigo  { color:#c7d2fe; border-color:#3730a3; background:#1e1b4b; }
  .badge-emerald { color:#bbf7d0; border-color:#065f46; background:#064e3b; }
  .badge-amber   { color:#fde68a; border-color:#92400e; background:#78350f; }
  .badge-red     { color:#fecaca; border-color:#7f1d1d; background:#7f1d1d33; }
  .badge-slate   { color:#cbd5e1; border-color:#475569; background:#0f172a; }

  /* Mini stats */
  .mini-stat{
    display:inline-flex; align-items:baseline; gap:.4rem;
    padding:.25rem .55rem;
    border-radius:.6rem;
    border:1px solid rgba(63,63,70,.9);
    background: rgba(24,24,27,.6);
    color:#e4e4e7;
    font-size:.75rem;
  }
  .mini-stat b{ font-size:.8rem; }

  /* Botones compactos */
  .btn{
    display:inline-flex; align-items:center; justify-content:center; gap:.35rem;
    padding:.35rem .65rem;
    border-radius:.55rem;
    border:1px solid rgba(63,63,70,.9);
    transition:.15s ease;
    font-size:.78rem;
    line-height:1.1;
    white-space:nowrap;
  }
  .btn-sky{ border-color: rgba(56,189,248,.35); color:#7dd3fc; }
  .btn-sky:hover{ background: rgba(12,74,110,.35); }
  .btn-amber{ border-color: rgba(245,158,11,.35); color:#fcd34d; }
  .btn-amber:hover{ background: rgba(120,53,15,.30); }
  .btn-indigo{ border-color: rgba(99,102,241,.35); color:#a5b4fc; }
  .btn-indigo:hover{ background: rgba(30,27,75,.35); }

  /* Tabla */
  .table-wrap{
    border:1px solid rgb(39 39 42);
    background: rgb(24 24 27);
    border-radius: 1rem;
    overflow:hidden;
    box-shadow: 0 8px 30px rgba(0,0,0,.35);
  }
  .thead-row{ background: rgb(39 39 42); color: rgb(212 212 216); }
  .tbody-row:nth-child(even){ background: rgba(39,39,42,.18); }
  .tbody-row:hover{ background: rgba(39,39,42,.45); }

  /* Filtros */
  .filter-box{
    border:1px solid rgb(39 39 42);
    background: rgba(24,24,27,.75);
    border-radius: 1rem;
    padding: .9rem;
  }
  .inp, .sel{
    width:100%;
    border-radius:.75rem;
    background: rgb(9 9 11);
    border:1px solid rgb(63 63 70);
    color: rgb(244 244 245);
    padding:.55rem .75rem;
    outline:none;
  }
  .inp:focus, .sel:focus{
    box-shadow: 0 0 0 2px rgba(16,185,129,.35);
    border-color: rgba(16,185,129,.55);
  }
  .label{
    font-size:.72rem;
    color: rgb(161 161 170);
    margin-bottom:.25rem;
    display:block;
  }
</style>
@endpush

@section('content')
@php
  $badgeModo = [
    'contado'  => ['label'=>'Contado',  'class'=>'badge-emerald'],
    'credito'  => ['label'=>'Crédito',  'class'=>'badge-indigo'],
    'tarjeta'  => ['label'=>'Tarjeta',  'class'=>'badge-amber'],
  ];
  $badgeEstado = [
    'pendiente'=> ['label'=>'Pendiente', 'class'=>'badge-amber'],
    'aprobado' => ['label'=>'Aprobado',  'class'=>'badge-emerald'],
    'rechazado'=> ['label'=>'Rechazado', 'class'=>'badge-red'],
    'anulado'  => ['label'=>'Anulado',   'class'=>'badge-slate'],
  ];

  $sumTotal = 0; $sumIva = 0; $sumGrav = 0;
  foreach($sales as $sx){
    $g = (int)($sx->gravada_10 ?? 0) + (int)($sx->gravada_5 ?? 0) + (int)($sx->exento ?? 0);
    $sumGrav += $g;
    $sumIva  += (int)($sx->total_iva ?? 0);
    $sumTotal+= (int)($sx->total ?? 0);
  }
@endphp

{{-- HEADER --}}
<div class="flex items-center justify-between mb-4">
  <div class="flex items-center gap-3">
    <h1 class="text-2xl font-bold text-emerald-400">📊 Ventas</h1>
    <div class="hidden md:flex items-center gap-2">
      <span class="mini-stat">Gravadas: <b>Gs. {{ number_format($sumGrav,0,',','.') }}</b></span>
      <span class="mini-stat">IVA: <b>Gs. {{ number_format($sumIva,0,',','.') }}</b></span>
      <span class="mini-stat">Total: <b class="text-emerald-300">Gs. {{ number_format($sumTotal,0,',','.') }}</b></span>
    </div>
  </div>

  <x-create-button route="{{ route('sales.create') }}" text="Nueva venta" />
</div>

<x-flash-message />

{{-- FILTROS --}}
<form method="GET" class="filter-box mb-4">
  <div class="grid md:grid-cols-12 gap-3 items-end">
    <div class="md:col-span-6">
      <label class="label">Búsqueda</label>
      <input class="inp" type="text" name="q" value="{{ request('q') }}" placeholder="🔎 Cliente, código, nota, #ID…" />
    </div>

    <div class="md:col-span-3">
      <label class="label">Estado</label>
      <select name="estado" class="sel">
        <option value="">— Todos —</option>
        @foreach(['pendiente','aprobado','rechazado','anulado'] as $e)
          <option value="{{ $e }}" @selected(request('estado')===$e)>{{ ucfirst($e) }}</option>
        @endforeach
      </select>
    </div>

    <div class="md:col-span-3 flex gap-2">
      <button class="px-4 py-2 rounded-lg bg-emerald-600 text-white">Filtrar</button>
      <a href="{{ route('sales.index') }}" class="px-4 py-2 rounded-lg border border-zinc-600 text-zinc-300">Limpiar</a>
    </div>
  </div>
</form>

{{-- TABLA --}}
<div class="table-wrap">

  {{-- 🔥 SCROLL SOLO DE LA TABLA --}}
  <div class="max-h-[65vh] overflow-y-auto">

    <div class="overflow-x-auto tbl-sticky">
      <table class="min-w-full text-sm">
        <thead>
          <tr class="thead-row uppercase text-[11px] tracking-wider">
            <th class="px-4 py-3 text-left w-20">ID</th>
            <th class="px-4 py-3 text-left min-w-[260px]">Cliente</th>
            <th class="px-4 py-3 text-left w-28">Modo</th>
            <th class="px-4 py-3 num w-36">Gravadas</th>
            <th class="px-4 py-3 num w-28">IVA</th>
            <th class="px-4 py-3 num w-40">Total</th>
            <th class="px-4 py-3 text-left w-34">Estado</th>
            <th class="px-4 py-3 text-left w-32">Fecha</th>
            <th class="px-4 py-3 text-right w-[260px]">Acciones</th>
          </tr>
        </thead>

        <tbody class="divide-y divide-zinc-800 text-zinc-200">
          @forelse($sales as $s)
            @php
              $gravadas = (int)($s->gravada_10 ?? 0) + (int)($s->gravada_5 ?? 0) + (int)($s->exento ?? 0);
              $modoCfg = $badgeModo[strtolower($s->modo_pago ?? '')] ?? ['label'=>$s->modo_pago,'class'=>'badge-slate'];
              $estadoCfg = $badgeEstado[strtolower($s->estado ?? '')] ?? ['label'=>$s->estado,'class'=>'badge-slate'];
              $fecha = optional($s->fecha)->format('Y-m-d') ?? optional($s->created_at)->format('Y-m-d');
            @endphp

            <tr class="tbody-row">
              <td class="px-4 py-3 mono">#{{ $s->id }}</td>
              <td class="px-4 py-3">{{ $s->client->name ?? '—' }}</td>
              <td class="px-4 py-3"><span class="badge {{ $modoCfg['class'] }}">{{ $modoCfg['label'] }}</span></td>
              <td class="px-4 py-3 num">Gs. {{ number_format($gravadas,0,',','.') }}</td>
              <td class="px-4 py-3 num">Gs. {{ number_format($s->total_iva ?? 0,0,',','.') }}</td>
              <td class="px-4 py-3 num text-emerald-300 font-semibold">Gs. {{ number_format($s->total ?? 0,0,',','.') }}</td>
              <td class="px-4 py-3"><span class="badge {{ $estadoCfg['class'] }}">{{ $estadoCfg['label'] }}</span></td>
              <td class="px-4 py-3 mono text-zinc-400">{{ $fecha }}</td>
              <td class="px-4 py-3 text-right">
                <a href="{{ route('sales.show',$s) }}" class="btn btn-sky">Ver</a>
                <a href="{{ route('sales.edit',$s) }}" class="btn btn-amber">Editar</a>
                <x-delete-button :action="route('sales.destroy',$s)" :name="'la venta #'.$s->id" />
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="9" class="px-4 py-10 text-center text-zinc-400 italic">
                🚫 No hay ventas registradas
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

  </div>

  {{-- PAGINACIÓN (FIJA) --}}
  <div class="p-4 border-t border-zinc-800 flex justify-between items-center">
    <div class="text-xs text-zinc-400">
      Mostrando {{ $sales->firstItem() ?? 0 }} a {{ $sales->lastItem() ?? 0 }} de {{ $sales->total() }}
    </div>
    {{ $sales->withQueryString()->links() }}
  </div>

</div>
@endsection
