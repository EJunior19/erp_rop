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

{{-- 🔍 Buscador PRO (con autofocus al recargar) --}}
<div 
  x-data="{
    q: '{{ request('q') }}',
    submit() {
      const params = new URLSearchParams()
      if (this.q) params.set('q', this.q)
      window.location = '{{ route('brands.index') }}?' + params.toString()
    }
  }"
  x-init="
    $nextTick(() => {
      if ($refs.search) {
        $refs.search.focus()
        const v = $refs.search.value || ''
        $refs.search.setSelectionRange(v.length, v.length)
      }
    })
  "
  class="mb-4"
>
  <input
    x-ref="search"
    type="text"
    placeholder="🔍 Buscar marca por nombre o código…"
    x-model="q"
    @input.debounce.500ms="submit"
    class="w-full md:w-1/2 rounded-lg bg-slate-800 border border-slate-600
           text-slate-100 placeholder-slate-400 px-4 py-2
           focus:outline-none focus:ring-2 focus:ring-emerald-500"
  />

  {{-- Limpiar --}}
  <template x-if="q">
    <div class="mt-2 text-right">
      <a href="{{ route('brands.index') }}"
         class="text-sm text-slate-400 hover:text-emerald-400 transition">
        Limpiar búsqueda ✖
      </a>
    </div>
  </template>
</div>


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
