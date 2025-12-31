{{-- resources/views/products/index.blade.php --}}
@extends('layout.admin')

@section('content')
<div class="flex items-center justify-between mb-6">
  <h1 class="text-2xl font-bold text-slate-100">📦 Productos</h1>

  {{-- Botón para crear nuevo producto --}}
  <x-create-button route="{{ route('products.create') }}" text="Nuevo producto" />
</div>
{{-- Buscador PRO --}}
<div 
  x-data="{
    q: '{{ request('q') }}',
    stock: '{{ request('stock','all') }}',
    submit() {
      const params = new URLSearchParams()
      if (this.q) params.set('q', this.q)
      if (this.stock !== 'all') params.set('stock', this.stock)
      window.location = '{{ route('products.index') }}?' + params.toString()
    }
  }"
  class="mb-4"
>
  <div class="grid grid-cols-1 md:grid-cols-4 gap-3">

    {{-- Input búsqueda --}}
    <input
      type="text"
      placeholder="🔍 Buscar por código, nombre, marca…"
      x-model="q"
      @input.debounce.500ms="submit"
      class="md:col-span-3 rounded-lg bg-slate-800 border border-slate-600
             text-slate-100 placeholder-slate-400 px-4 py-2
             focus:outline-none focus:ring-2 focus:ring-emerald-500"
    />

    {{-- Filtro stock --}}
    <select
      x-model="stock"
      @change="submit"
      class="rounded-lg bg-slate-800 border border-slate-600
             text-slate-100 px-3 py-2 focus:outline-none
             focus:ring-2 focus:ring-emerald-500"
    >
      <option value="all">📦 Todo el stock</option>
      <option value="zero">❌ Sin stock</option>
      <option value="low">⚠️ Stock bajo (≤5)</option>
    </select>

  </div>

  {{-- Limpiar --}}
  <template x-if="q || stock !== 'all'">
    <div class="mt-2 text-right">
      <a href="{{ route('products.index') }}"
         class="text-sm text-slate-400 hover:text-emerald-400 transition">
        Limpiar filtros ✖
      </a>
    </div>
  </template>
</div>


{{-- Mensajes flash --}}
<x-flash-message />

<div class="bg-slate-900 text-slate-100 rounded-xl shadow-xl border border-slate-700">

  {{-- Scroll SOLO de la tabla --}}
  <div class="max-h-[70vh] overflow-y-auto overflow-x-auto rounded-t-xl">

    <table class="min-w-full text-sm text-left">
      <thead class="bg-slate-800 text-slate-200 uppercase text-[11px] tracking-wider sticky top-0 z-10 border-b border-slate-600">
        <tr>
          <th class="px-4 py-3">#</th>
          <th class="px-4 py-3">Código</th>
          <th class="px-4 py-3">Nombre</th>
          <th class="px-4 py-3">Marca</th>
          <th class="px-4 py-3">Categoría</th>
          <th class="px-4 py-3">Proveedor</th>
          <th class="px-4 py-3 text-right">Precio</th>
          <th class="px-4 py-3 text-right">Stock</th>
          <th class="px-4 py-3 text-right">Acciones</th>
        </tr>
      </thead>

      <tbody class="divide-y divide-slate-700">
        @forelse($products as $p)
          @php
            $stock = (int) ($p->stock ?? 0);
            $stockColor =
              $stock <= 0 ? 'bg-red-600 text-red-50' :
              ($stock <= 5 ? 'bg-amber-400 text-amber-950' :
              'bg-emerald-500 text-emerald-950');
          @endphp

          <tr class="odd:bg-slate-900 even:bg-slate-800/40 hover:bg-slate-700/40 transition">
            <td class="px-4 py-3 font-mono text-slate-300">{{ $p->id }}</td>

            <td class="px-4 py-3 font-mono text-slate-200">
              {{ $p->code ?? '—' }}
            </td>

            <td class="px-4 py-3 font-medium text-slate-100">
              {{ $p->name }}
            </td>

            <td class="px-4 py-3 text-slate-300">
              {{ $p->brand->name ?? '—' }}
            </td>

            <td class="px-4 py-3 text-slate-300">
              {{ $p->category->name ?? '—' }}
            </td>

            <td class="px-4 py-3 text-slate-300">
              {{ $p->supplier->name ?? '—' }}
            </td>

            {{-- Precio --}}
            <td class="px-4 py-3 text-right font-semibold text-emerald-400">
              @if(!is_null($p->price_cash))
                @money($p->price_cash)
              @else
                —
              @endif
            </td>

            {{-- 🔢 Stock SOLO número --}}
            <td class="px-4 py-3 text-right">
              <span class="inline-flex items-center justify-center w-8 h-8 rounded-full text-sm font-bold {{ $stockColor }}">
                {{ $stock }}
              </span>
            </td>

            {{-- Acciones --}}
            <td class="px-4 py-3 text-right whitespace-nowrap">
              <x-action-buttons 
                :show="route('products.show',$p)"
                :edit="route('products.edit',$p)"
                :delete="route('products.destroy',$p)"
                :name="'el producto '.$p->name" />
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="9" class="px-6 py-10 text-center text-slate-400 italic">
              Sin productos
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  {{-- Paginación (fija, no scrollea) --}}
  <div class="p-4 border-t border-slate-700">
    {{ $products->links() }}
  </div>
</div>
@endsection
