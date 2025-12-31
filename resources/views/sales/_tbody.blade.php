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
@endphp

@forelse($sales as $s)
  @php
    $gravadas = (int)($s->gravada_10 ?? 0) + (int)($s->gravada_5 ?? 0) + (int)($s->exento ?? 0);
    $modoCfg = $badgeModo[strtolower($s->modo_pago ?? '')] ?? ['label'=>$s->modo_pago,'class'=>'badge-slate'];
    $estadoCfg = $badgeEstado[strtolower($s->status ?? '')] ?? ['label'=>$s->status,'class'=>'badge-slate']; {{-- ✅ status --}}
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
