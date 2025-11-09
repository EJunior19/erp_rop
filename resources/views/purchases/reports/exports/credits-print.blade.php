@extends('layout.print')

@section('content')
  <h2 class="text-xl font-bold mb-4">💳 Reporte de Cuentas por Cobrar</h2>

  <table class="w-full border-collapse border border-gray-400 text-sm">
    <thead class="bg-gray-100">
      <tr>
        <th class="border px-2 py-1">#</th>
        <th class="border px-2 py-1">Cliente</th>
        <th class="border px-2 py-1">Monto</th>
        <th class="border px-2 py-1">Vencimiento</th>
        <th class="border px-2 py-1">Estado</th>
      </tr>
    </thead>
    <tbody>
      @foreach($credits as $c)
        <tr>
          <td class="border px-2 py-1">{{ $c->id }}</td>
          <td class="border px-2 py-1">{{ $c->client->name ?? '—' }}</td>
          <td class="border px-2 py-1">Gs. {{ number_format($c->monto, 0, ',', '.') }}</td>
          <td class="border px-2 py-1">{{ $c->fecha_vencimiento->format('Y-m-d') }}</td>
          <td class="border px-2 py-1">{{ ucfirst($c->estado) }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>
@endsection
