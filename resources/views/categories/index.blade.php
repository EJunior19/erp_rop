{{-- resources/views/categories/index.blade.php --}}
@extends('layout.admin')

@section('content')
<div class="flex items-center justify-between mb-6">
  <h1 class="text-2xl font-bold text-slate-100 flex items-center gap-2">
    📂 Categorías
  </h1>

  {{-- Botón para crear nueva categoría --}}
  <x-create-button route="{{ route('categories.create') }}" text="Nueva categoría" />
</div>

{{-- 🔍 Buscador PRO (con autofocus al recargar) --}}
<div 
  x-data="{
    q: '{{ request('q') }}',
    submit() {
      const params = new URLSearchParams()
      if (this.q) params.set('q', this.q)
      window.location = '{{ route('categories.index') }}?' + params.toString()
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
    placeholder="🔍 Buscar categoría por nombre o código…"
    x-model="q"
    @input.debounce.500ms="submit"
    class="w-full md:w-1/2 rounded-lg bg-slate-800 border border-slate-600
           text-slate-100 placeholder-slate-400 px-4 py-2
           focus:outline-none focus:ring-2 focus:ring-emerald-500"
  />

  {{-- Limpiar --}}
  <template x-if="q">
    <div class="mt-2 text-right">
      <a href="{{ route('categories.index') }}"
         class="text-sm text-slate-400 hover:text-emerald-400 transition">
        Limpiar búsqueda ✖
      </a>
    </div>
  </template>
</div>

{{-- Mensajes flash --}}
<x-flash-message />

<div class="bg-slate-900 text-white rounded-xl shadow-md border border-slate-700">

  {{-- 🔹 CONTENEDOR SCROLLEABLE SOLO PARA LA TABLA --}}
  <div class="max-h-[65vh] overflow-y-auto overflow-x-auto rounded-t-xl">

    <table class="min-w-full text-sm text-left">
      <thead class="bg-slate-800 text-slate-200 uppercase text-xs tracking-wide sticky top-0 z-10">
        <tr>
          <th class="px-6 py-3">Id</th>
          <th class="px-6 py-3">Código</th>
          <th class="px-6 py-3">Nombre</th>
          <th class="px-6 py-3">Activo</th>
          <th class="px-6 py-3 text-right">Acciones</th>
        </tr>
      </thead>

      <tbody class="divide-y divide-slate-800">
        @forelse($categories as $c)
          <tr class="hover:bg-slate-800/60 transition">
            <td class="px-6 py-3 font-mono text-slate-300">
              {{ $c->id }}
            </td>

            <td class="px-6 py-3 font-mono">
              {{ $c->code }}
            </td>

            <td class="px-6 py-3 font-medium">
              {{ $c->name }}
            </td>

            <td class="px-6 py-3">
              <x-table-row-status :active="$c->active" />
            </td>

            <td class="px-6 py-3 text-right">
              <x-action-buttons 
                :show="route('categories.show',$c)"
                :edit="route('categories.edit',$c)"
                :delete="route('categories.destroy',$c)"
                :name="'la categoría '.$c->name" />
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="5" class="px-6 py-10 text-center text-slate-400 italic">
              Sin categorías
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>

  </div>

  {{-- 🔹 Paginación fija, fuera del scroll --}}
  <div class="p-4 border-t border-slate-700">
    {{ $categories->links() }}
  </div>
</div>
@endsection
