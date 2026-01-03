@extends('layout.admin')
@section('content')

<h1 class="text-2xl font-bold text-white mb-4">📊 Panel principal</h1>

{{-- KPIs --}}
<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">

  {{-- Ventas hoy --}}
  <div class="bg-gradient-to-br from-emerald-600 to-emerald-500 text-white rounded-xl shadow p-4">
    <div class="font-medium">Ventas de hoy</div>
    <div class="text-3xl font-bold">{{ $ventasHoy }}</div>
    <div class="text-sm mt-3 border-t border-white/20 pt-2 flex items-center justify-between">
      <a href="{{ route('sales.index') }}" class="hover:underline">Ver ventas</a>
      <span class="text-xs px-2 py-0.5 rounded bg-black/30">
        Gs. {{ number_format((int)$montoVentasHoy,0,',','.') }}
      </span>
    </div>
  </div>

  {{-- Ventas mes --}}
  <div class="bg-gradient-to-br from-blue-600 to-blue-500 text-white rounded-xl shadow p-4">
    <div class="font-medium">Ventas del mes</div>
    <div class="text-3xl font-bold">{{ $ventasMes }}</div>
    <div class="text-sm mt-3 border-t border-white/20 pt-2 flex items-center justify-between">
      <a href="{{ route('sales.index') }}" class="hover:underline">Ver ventas</a>
      <span class="text-xs px-2 py-0.5 rounded bg-black/30">
        Gs. {{ number_format((int)$montoVentasMes,0,',','.') }}
      </span>
    </div>
  </div>

  {{-- Pendientes --}}
  <div class="bg-gradient-to-br from-amber-600 to-amber-500 text-white rounded-xl shadow p-4">
    <div class="font-medium">Pendientes aprobación</div>
    <div class="text-3xl font-bold">{{ $ventasPendientes }}</div>
    <div class="text-sm mt-3 border-t border-white/20 pt-2 flex items-center justify-between">
      <a href="{{ route('sales.index',['status'=>'pendiente_aprobacion']) }}" class="hover:underline">Ver pendientes</a>
      <i class="fa-solid fa-hourglass-half"></i>
    </div>
  </div>

  {{-- Stock bajo --}}
  <div class="bg-gradient-to-br from-purple-600 to-purple-500 text-white rounded-xl shadow p-4">
    <div class="font-medium">Stock bajo (≤ {{ $stockMin }})</div>
    <div class="text-3xl font-bold">{{ $stockBajo }}</div>
    <div class="text-sm mt-3 border-t border-white/20 pt-2 flex items-center justify-between">
      <a href="{{ route('products.index') }}" class="hover:underline">Ver productos</a>
      <i class="fa-solid fa-boxes-stacked"></i>
    </div>
  </div>

</div>

{{-- Accesos rápidos --}}
<div class="bg-gray-900 rounded-xl shadow border border-gray-700 mb-6">
  <div class="px-4 py-2 border-b border-gray-700 font-semibold text-gray-200">
    ⚡ Accesos rápidos
  </div>
  <div class="p-4 flex flex-wrap gap-2">
    <a href="{{ route('sales.create') }}" class="px-3 py-1.5 text-sm border border-emerald-600 text-emerald-300 rounded-lg hover:bg-emerald-700/30 transition">➕ Nueva venta</a>
    <a href="{{ route('purchase_orders.index') }}" class="px-3 py-1.5 text-sm border border-indigo-600 text-indigo-300 rounded-lg hover:bg-indigo-700/30 transition">🧾 Nueva compra</a>
    <a href="{{ route('inventory.index') }}" class="px-3 py-1.5 text-sm border border-purple-600 text-purple-300 rounded-lg hover:bg-purple-700/30 transition">📦 Movimiento inventario</a>
    <a href="{{ route('clients.index') }}" class="px-3 py-1.5 text-sm border border-gray-600 text-gray-300 rounded-lg hover:bg-gray-700 hover:text-white transition">👥 Clientes</a>
    <a href="{{ route('suppliers.index') }}" class="px-3 py-1.5 text-sm border border-gray-600 text-gray-300 rounded-lg hover:bg-gray-700 hover:text-white transition">🏢 Proveedores</a>
    <a href="{{ route('products.index') }}" class="px-3 py-1.5 text-sm border border-gray-600 text-gray-300 rounded-lg hover:bg-gray-700 hover:text-white transition">📦 Productos</a>
  </div>
</div>

{{-- 3 columnas: ventas / compras / inventario --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

  {{-- Últimas ventas --}}
  <div class="bg-gray-900 rounded-xl shadow border border-gray-700">
    <div class="px-4 py-2 border-b border-gray-700 font-semibold text-gray-200 flex items-center justify-between">
      <span>🧾 Últimas ventas</span>
      <a href="{{ route('sales.index') }}" class="text-xs text-emerald-300 hover:underline">ver todo</a>
    </div>
    <div class="p-4 overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="text-gray-400">
          <tr>
            <th class="text-left p-2">#</th>
            <th class="text-left p-2">Cliente</th>
            <th class="text-right p-2">Total</th>
          </tr>
        </thead>
        <tbody>
          @forelse($ultimasVentas as $v)
          <tr class="border-t border-gray-800">
            <td class="p-2 text-gray-300">#{{ $v->id }}</td>
            <td class="p-2 text-gray-200">{{ $v->client_name }}</td>
            <td class="p-2 text-right text-gray-200 font-semibold">Gs. {{ number_format((int)$v->total,0,',','.') }}</td>
          </tr>
          @empty
          <tr><td class="p-3 text-gray-400" colspan="3">Sin ventas recientes.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{-- Últimas compras (purchase_orders) --}}
  <div class="bg-gray-900 rounded-xl shadow border border-gray-700">
    <div class="px-4 py-2 border-b border-gray-700 font-semibold text-gray-200 flex items-center justify-between">
      <span>🧾 Últimas compras</span>
      <a href="{{ route('purchase_orders.index') }}" class="text-xs text-indigo-300 hover:underline">ver todo</a>
    </div>
    <div class="p-4 overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="text-gray-400">
          <tr>
            <th class="text-left p-2">#</th>
            <th class="text-left p-2">Proveedor</th>
            <th class="text-right p-2">Total</th>
          </tr>
        </thead>
        <tbody>
          @forelse($ultimasCompras as $c)
          <tr class="border-t border-gray-800">
            <td class="p-2 text-gray-300">#{{ $c->id }}</td>
            <td class="p-2 text-gray-200">{{ $c->supplier_name }}</td>
            <td class="p-2 text-right text-gray-200 font-semibold">Gs. {{ number_format((int)$c->total,0,',','.') }}</td>
          </tr>
          @empty
          <tr><td class="p-3 text-gray-400" colspan="3">Sin compras recientes.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{-- Inventario reciente --}}
  <div class="bg-gray-900 rounded-xl shadow border border-gray-700">
    <div class="px-4 py-2 border-b border-gray-700 font-semibold text-gray-200 flex items-center justify-between">
      <span>📦 Inventario reciente</span>
      <a href="{{ route('inventory.index') }}" class="text-xs text-purple-300 hover:underline">ver todo</a>
    </div>
    <div class="p-4 overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="text-gray-400">
          <tr>
            <th class="text-left p-2">Fecha</th>
            <th class="text-left p-2">Producto</th>
            <th class="text-right p-2">Qty</th>
          </tr>
        </thead>
        <tbody>
          @forelse($ultimosMov as $m)
          <tr class="border-t border-gray-800">
            <td class="p-2 text-gray-300">
              {{ \Carbon\Carbon::parse($m->created_at)->format('d/m H:i') }}
            </td>
            <td class="p-2 text-gray-200">{{ $m->product_name }}</td>
            <td class="p-2 text-right text-gray-200 font-semibold">{{ (int)$m->qty }}</td>
          </tr>
          @empty
          <tr><td class="p-3 text-gray-400" colspan="3">Sin movimientos recientes.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

</div>

@endsection
