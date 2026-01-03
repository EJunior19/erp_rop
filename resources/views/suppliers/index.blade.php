{{-- resources/views/suppliers/index.blade.php --}}
@extends('layout.admin')

@section('content')
<div class="w-full px-6">
  {{-- Header --}}
  <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
    <h1 class="text-3xl font-bold text-green-400 flex items-center gap-2">
      🧾 Proveedores
      @isset($suppliers)
        <span class="text-sm font-medium text-gray-400">({{ number_format($suppliers->total()) }} registros)</span>
      @endisset
    </h1>

    <div class="flex items-center gap-2">
      {{-- Acciones rápidas --}}
      <x-create-button route="{{ route('suppliers.create') }}" text="Nuevo proveedor" />
    </div>
  </div>

  {{-- Flash --}}
  <x-flash-message />

  {{-- Filtros / Búsqueda --}}
  <div class="bg-gray-900 text-white rounded-xl shadow-2xl border-2 border-green-400 mb-4">
    <form method="GET" action="{{ route('suppliers.index') }}" class="p-4"
          x-data
          x-init="
            $nextTick(() => {
              const input = document.getElementById('q');
              if (!input) return;

              // ✅ Auto-focus al recargar
              input.focus({ preventScroll: true });

              // ✅ Cursor al final (ideal si ya hay texto)
              const v = input.value || '';
              input.setSelectionRange(v.length, v.length);
            })
          "
    >
      <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
        {{-- Búsqueda libre --}}
        <div class="md:col-span-6">
          <label for="q" class="block text-xs uppercase tracking-wide text-gray-400 mb-1">Buscar</label>
          <input
            id="q" name="q" type="text"
            value="{{ request('q') }}"
            placeholder="Nombre, código, RUC, email o teléfono…"
            class="w-full rounded-lg bg-gray-800 border border-gray-700 focus:border-green-400 focus:ring-0 px-3 py-2 text-sm placeholder-gray-500"
          />
        </div>

        {{-- Filtro activo --}}
        <div class="md:col-span-3">
          <label for="active" class="block text-xs uppercase tracking-wide text-gray-400 mb-1">Estado</label>
          <select
            id="active" name="active"
            class="w-full rounded-lg bg-gray-800 border border-gray-700 focus:border-green-400 focus:ring-0 px-3 py-2 text-sm"
          >
            <option value="" {{ request('active') === null || request('active') === '' ? 'selected' : '' }}>Todos</option>
            <option value="1" {{ request('active') === '1' ? 'selected' : '' }}>Activos</option>
            <option value="0" {{ request('active') === '0' ? 'selected' : '' }}>Inactivos</option>
          </select>
        </div>

        {{-- Botones --}}
        <div class="md:col-span-3 flex gap-2">
          <button type="submit"
                  class="inline-flex items-center justify-center px-4 py-2 rounded-lg bg-green-500/20 border border-green-400 text-green-300 hover:bg-green-500/30 text-sm font-medium transition">
            Buscar
          </button>
          <a href="{{ route('suppliers.index') }}"
             class="inline-flex items-center justify-center px-4 py-2 rounded-lg bg-gray-800 border border-gray-700 text-gray-300 hover:bg-gray-700 text-sm font-medium transition">
            Limpiar
          </a>
        </div>
      </div>
    </form>
  </div>

  {{-- Tabla --}}
  <div class="bg-gray-900 text-white rounded-xl shadow-2xl border-2 border-green-400">
    <div class="relative">
      {{-- contenedor con scroll y header sticky --}}
      <div class="overflow-x-auto max-h-[70vh] rounded-t-xl">
        <table class="min-w-full text-sm text-left">
          <thead class="bg-gray-800 text-gray-200 uppercase text-xs tracking-wide sticky top-0 z-10">
            <tr>
              <th scope="col" class="px-6 py-3 text-right w-16">ID</th>
              <th scope="col" class="px-6 py-3">Código</th>
              <th scope="col" class="px-6 py-3">Nombre</th>
              <th scope="col" class="px-6 py-3">RUC</th>
              <th scope="col" class="px-6 py-3">Email</th>
              <th scope="col" class="px-6 py-3">Teléfono</th>
              <th scope="col" class="px-6 py-3">Activo</th>
              <th scope="col" class="px-6 py-3 text-right w-40">Acciones</th>
            </tr>
          </thead>

          <tbody class="divide-y divide-gray-700">
            @forelse($suppliers as $s)
              <tr class="hover:bg-gray-800/60">
                <td class="px-6 py-3 text-right tabular-nums">{{ $s->id }}</td>
                <td class="px-6 py-3 font-mono whitespace-nowrap">{{ $s->code ?? '—' }}</td>
                <td class="px-6 py-3 font-medium">{{ $s->name }}</td>
                <td class="px-6 py-3 whitespace-nowrap">{{ $s->ruc ?? '—' }}</td>
                <td class="px-6 py-3">
                  <span class="break-all">{{ $s->email_main ?? '—' }}</span>
                </td>
                <td class="px-6 py-3 whitespace-nowrap">{{ $s->phone_main ?? '—' }}</td>
                <td class="px-6 py-3">
                  <x-table-row-status :active="$s->active" />
                </td>
                <td class="px-6 py-3">
                  <div class="flex justify-end">
                    <x-action-buttons
                      :show="route('suppliers.show', $s)"
                      :edit="route('suppliers.edit', $s)"
                      :delete="route('suppliers.destroy', $s)"
                      :name="'el proveedor '.$s->name"
                    />
                  </div>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="8" class="px-6 py-12">
                  <div class="flex flex-col items-center justify-center text-center text-gray-400">
                    <div class="text-5xl mb-3">🗂️</div>
                    <p class="font-semibold">Sin proveedores</p>
                    <p class="text-sm">Agregá tu primer proveedor para comenzar.</p>
                  </div>
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      {{-- Paginación --}}
      <div class="p-4 border-t border-gray-700">
        {{ $suppliers->appends(request()->query())->links() }}
      </div>
    </div>
  </div>
</div>
@endsection
