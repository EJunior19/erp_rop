@extends('layout.print')

@section('content')
  <h2 class="text-xl font-bold mb-4">📦 Reporte de Inventario</h2>

  <table class="w-full border-collapse border border-gray-400 text-sm">
    <thead class="bg-gray-100">
      <tr>
        <th class="border px-2 py-1">#</th>
        <th class="border px-2 py-1">Producto</th>
        <th class="border px-2 py-1">Cantidad</th>
        <th class="border px-2 py-1">Movimiento</th>
        <th class="border px-2 py-1">Fecha</th>
      </tr>
    </thead>
    <tbody>
      @foreach($inventory as $i)
        <tr>
          <td class="border px-2 py-1">{{ $i->id }}</td>
          <td class="border px-2 py-1">{{ $i->product->name ?? '—' }}</td>
          <td class="border px-2 py-1">{{ $i->cantidad }}</td>
          <td class="border px-2 py-1">{{ ucfirst($i->tipo) }}</td>
          <td class="border px-2 py-1">{{ $i->created_at->format('Y-m-d') }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>
@endsection
