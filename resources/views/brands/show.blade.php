{{-- resources/views/brands/show.blade.php --}}
@extends('layout.admin')

@section('content')
<div class="w-full px-6">
  <h1 class="text-3xl font-bold mb-6 text-green-400 flex items-center gap-2">
    🏷️ Marca #{{ $brand->id }}
  </h1>

  <div class="bg-gray-900 text-white rounded-xl shadow-2xl p-8 border-2 border-green-400 w-full">
    {{-- Datos principales --}}
    <div class="grid md:grid-cols-2 gap-8 text-lg">
      <p>
        <span class="font-semibold text-green-300">Código:</span>
        <span class="font-mono text-xl">{{ $brand->code ?? '—' }}</span>
      </p>

      <p>
        <span class="font-semibold text-green-300">Nombre:</span>
        <span class="text-xl">{{ $brand->name }}</span>
      </p>

      <p class="flex items-center gap-2">
        <span class="font-semibold text-green-300">Activo:</span>
        <x-table-row-status :active="$brand->active" />
      </p>

      <p>
        <span class="font-semibold text-green-300">Productos asociados:</span>
        {{ $brand->products_count ?? ($brand->products->count() ?? 0) }}
      </p>

      <p class="md:col-span-2 text-gray-400 text-base">
        📅 Creado: {{ $brand->created_at?->format('d/m/Y H:i') }} ·
        🔄 Actualizado: {{ $brand->updated_at?->format('d/m/Y H:i') }}
      </p>
    </div>

    {{-- Acciones --}}
    <div class="flex flex-wrap gap-4 mt-10">
      <x-action-buttons
        :edit="route('brands.edit', $brand)"
        :delete="route('brands.destroy', $brand)"
        :name="'la marca '.$brand->name" />

      {{-- Activar/Desactivar (si tenés route brands.toggle) --}}
      @isset($brand->active)
        <form method="POST" action="{{ route('brands.update', $brand) }}">
          @csrf @method('PUT')
          <input type="hidden" name="name" value="{{ $brand->name }}">
          <input type="hidden" name="active" value="{{ $brand->active ? 0 : 1 }}">
          <button class="px-6 py-2 text-sm rounded-lg border border-purple-500 text-purple-400 hover:bg-purple-500 hover:text-white font-semibold shadow">
            {{ $brand->active ? 'Desactivar' : 'Activar' }}
          </button>
        </form>
      @endisset

      <a href="{{ route('brands.index') }}"
         class="px-6 py-2 text-sm rounded-lg border border-gray-500 text-gray-300 hover:bg-gray-600 font-semibold shadow">
        ← Volver
      </a>
    </div>

    {{-- Últimos productos de la marca --}}
    <div class="mt-12">
      <h2 class="text-2xl font-semibold text-green-300 mb-4">🧾 Últimos productos</h2>

      @php
        // Soporte por si el controlador no envió $latestProducts
        $lp = isset($latestProducts) ? $latestProducts
             : (method_exists($brand, 'products')
                ? $brand->products()->latest('id')->take(10)->get(['id','code','name','active'])
                : collect());
      @endphp

      @if($lp->count())
        <div class="overflow-x-auto rounded-lg border border-gray-700">
          <table class="min-w-full text-base text-left">
            <thead class="bg-gray-800 text-gray-200 uppercase text-xs tracking-wide">
              <tr>
                <th class="px-5 py-3">ID</th>
                <th class="px-5 py-3">Código</th>
                <th class="px-5 py-3">Nombre</th>
                <th class="px-5 py-3">Activo</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-700">
              @foreach($lp as $p)
                <tr class="hover:bg-gray-800/60 transition">
                  <td class="px-5 py-3">{{ $p->id }}</td>
                  <td class="px-5 py-3 font-mono">{{ $p->code ?? '—' }}</td>
                  <td class="px-5 py-3">{{ $p->name }}</td>
                  <td class="px-5 py-3">
                    <x-table-row-status :active="$p->active" />
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @else
        <p class="text-gray-400 text-lg">No hay productos recientes para esta marca.</p>
      @endif
    </div>
  </div>
</div>
@endsection
