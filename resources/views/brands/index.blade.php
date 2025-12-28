{{-- resources/views/brands/index.blade.php --}}
@extends('layout.admin')

@section('content')
<div class="flex items-center justify-between mb-6">
  <h1 class="text-2xl font-bold text-slate-100 flex items-center gap-2">
    🏷️ Marcas
  </h1>

  {{-- Botón para crear nueva marca --}}
  <x-create-button route="{{ route('brands.create') }}" text="Nueva marca" />
</div>

{{-- Mensajes flash --}}
<x-flash-message />

<div class="bg-slate-900 text-white rounded-xl shadow-md border border-slate-700">

  {{-- 🔹 CONTENEDOR SCROLLEABLE SOLO PARA LA TABLA --}}
  <div class="max-h-[65vh] overflow-y-auto overflow-x-auto rounded-t-xl">

    <table class="min-w-full text-sm text-left">
      <thead class="bg-slate-800 text-slate-200 uppercase text-xs tracking-wide sticky top-0 z-10">
        <tr>
          <th class="px-6 py-3">ID</th>
          <th class="px-6 py-3">Código</th>
          <th class="px-6 py-3">Nombre</th>
          <th class="px-6 py-3 text-center">Productos</th>
          <th class="px-6 py-3">Activo</th>
          <th class="px-6 py-3 text-right">Acciones</th>
        </tr>
      </thead>

      <tbody class="divide-y divide-slate-800">
        @forelse($brands as $b)
          <tr class="hover:bg-slate-800/60 transition">
            <td class="px-6 py-3 font-mono text-slate-300">
              {{ $b->id }}
            </td>

            <td class="px-6 py-3 font-mono">
              {{ $b->code ?? '—' }}
            </td>

            <td class="px-6 py-3 font-medium">
              {{ $b->name }}
            </td>

            <td class="px-6 py-3 text-center">
              <span class="inline-flex items-center justify-center px-2 py-1 text-xs rounded-full
                bg-slate-800 border border-slate-600 text-slate-200">
                {{ $b->products_count ?? ($b->products->count() ?? 0) }}
              </span>
            </td>

            <td class="px-6 py-3">
              <x-table-row-status :active="$b->active" />
            </td>

            <td class="px-6 py-3 text-right">
              <x-action-buttons 
                :show="route('brands.show',$b)"
                :edit="route('brands.edit',$b)"
                :delete="route('brands.destroy',$b)"
                :name="'la marca '.$b->name" />
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="6" class="px-6 py-10 text-center text-slate-400 italic">
              No hay marcas registradas.
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>

  </div>

  {{-- 🔹 Paginación fija --}}
  <div class="p-4 border-t border-slate-700">
    {{ $brands->links() }}
  </div>
</div>
@endsection
