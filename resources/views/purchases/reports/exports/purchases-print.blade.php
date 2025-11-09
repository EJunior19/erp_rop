@extends('layout.print')

@section('content')
  <h2 class="text-xl font-bold mb-4">🛒 Reporte de Compras</h2>

  <table class="w-full border-collapse border border-gray-400 text-sm">
    <thead class="bg-gray-100">
      <tr>
        <th class="border px-2 py-1">#</th>
        <th class="border px-2 py-1">Proveedor</th>
        <th class="border px-2 py-1">Total</th>
        <th class="border px-2 py-1">Fecha</th>
      </tr>
    </thead>
    <tbody>
      @foreach($purchases as $p)
        <tr>
          <td class="border px-2 py-1">{{ $p->id }}</td>
          <td class="border px-2 py-1">{{ $p->supplier->name ?? '—' }}</td>
          <td class="border px-2 py-1">Gs. {{ number_format($p->total, 0, ',', '.') }}</td>
          <td class="border px-2 py-1">{{ $p->created_at->format('Y-m-d') }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>
@endsection
