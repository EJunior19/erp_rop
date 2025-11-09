@extends('layout.print')

@section('content')
  <h2 class="text-xl font-bold mb-4">🧾 Reporte de Ventas</h2>
  <p>Período: {{ $from ?? '—' }} al {{ $to ?? '—' }}</p>

  <table class="w-full border-collapse border border-gray-400 text-sm">
    <thead class="bg-gray-100">
      <tr>
        <th class="border px-2 py-1">#</th>
        <th class="border px-2 py-1">Cliente</th>
        <th class="border px-2 py-1">Total</th>
        <th class="border px-2 py-1">Fecha</th>
      </tr>
    </thead>
    <tbody>
      @foreach($sales as $s)
        <tr>
          <td class="border px-2 py-1">{{ $s->id }}</td>
          <td class="border px-2 py-1">{{ $s->client->name ?? '—' }}</td>
          <td class="border px-2 py-1">Gs. {{ number_format($s->total, 0, ',', '.') }}</td>
          <td class="border px-2 py-1">{{ $s->created_at->format('Y-m-d') }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>
@endsection
