@extends('layout.admin')

@section('content')
<div class="w-full px-6">

  {{-- TÍTULO --}}
  <div class="flex items-center gap-2 mb-4">
    <h1 class="text-3xl font-bold text-green-400">👥 Lista de Clientes</h1>
  </div>

  {{-- BOTÓN --}}
  <div class="mb-3">
    <a href="{{ route('clients.create') }}"
       class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded">
      + Nuevo Cliente
    </a>
  </div>

  {{-- FILTROS PRO (AJAX + SIN PERDER FOCO) --}}
  <form
    class="mb-6 w-full bg-[#0f172a] text-white border border-green-700/30 rounded-xl p-4"
    x-data="{
      q: '{{ request('q') }}',
      status: '{{ request('status','all') }}',
      test: '{{ request('test','all') }}',
      per_page: '{{ (int) request('per_page',25) }}',
      page: {{ (int) request('page',1) }},
      loading: false,

      buildParams() {
        const p = new URLSearchParams()
        if (this.q) p.set('q', this.q)
        if (this.status !== 'all') p.set('status', this.status)
        if (this.test !== 'all') p.set('test', this.test)
        if (this.per_page !== '25') p.set('per_page', this.per_page)
        if (this.page > 1) p.set('page', this.page)
        return p
      },

      async fetchList(pushState = true) {
        this.loading = true
        const params = this.buildParams()

        if (pushState) {
          history.replaceState(null, '', '{{ route('clients.index') }}?' + params.toString())
        }

        const res = await fetch('{{ route('clients.index') }}?' + params.toString(), {
          headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })

        const data = await res.json()
        document.getElementById('clients-tbody').innerHTML = data.tbody
        document.getElementById('clients-pagination').innerHTML = data.pagination
        this.loading = false
      },

      onFilterChange() {
        this.page = 1
        this.fetchList()
      },

      goToPage(p) {
        this.page = p
        this.fetchList()
      }
    }"
    @submit.prevent="onFilterChange()"
  >

    <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">

      {{-- Buscar --}}
      <div class="md:col-span-5">
        <label class="block text-sm text-green-300 mb-1">Buscar</label>
        <input
          type="text"
          x-model="q"
          @input.debounce.500ms="onFilterChange()"
          placeholder="Nombre, email, teléfono, código, RUC…"
          class="w-full bg-gray-800 border border-gray-700 rounded px-3 py-2
                 text-white placeholder-gray-400 focus:ring-2 focus:ring-green-600">
      </div>

      {{-- Estado --}}
      <div class="md:col-span-2">
        <label class="block text-sm text-green-300 mb-1">Estado</label>
        <select x-model="status" @change="onFilterChange()"
          class="w-full bg-gray-800 border border-gray-700 rounded px-3 py-2 focus:ring-2 focus:ring-green-600">
          <option value="all">Todos</option>
          <option value="active">Activos</option>
          <option value="inactive">Inactivos</option>
        </select>
      </div>

      {{-- Tipo --}}
      <div class="md:col-span-2">
        <label class="block text-sm text-green-300 mb-1">Tipo</label>
        <select x-model="test" @change="onFilterChange()"
          class="w-full bg-gray-800 border border-gray-700 rounded px-3 py-2 focus:ring-2 focus:ring-green-600">
          <option value="all">Todos</option>
          <option value="prod">Producción</option>
          <option value="test">Prueba</option>
        </select>
      </div>

      {{-- Por página --}}
      <div class="md:col-span-1">
        <label class="block text-sm text-green-300 mb-1">Pág.</label>
        <select x-model="per_page" @change="onFilterChange()"
          class="w-full bg-gray-800 border border-gray-700 rounded px-3 py-2 focus:ring-2 focus:ring-green-600">
          @foreach([10,25,50,100] as $n)
            <option value="{{ $n }}">{{ $n }}</option>
          @endforeach
        </select>
      </div>

      {{-- Botones --}}
      <div class="md:col-span-2 flex gap-2">
        <button type="submit"
          class="w-full bg-green-600 hover:bg-green-700 text-white rounded px-4 py-2">
          <span x-show="!loading">🔎 Buscar</span>
          <span x-show="loading">⏳</span>
        </button>

        <a href="{{ route('clients.index') }}"
          class="w-full bg-gray-700 hover:bg-gray-600 text-white rounded px-4 py-2 text-center">
          ✖ Limpiar
        </a>
      </div>

    </div>
  </form>

  {{-- FLASH --}}
  @if(session('success'))
    <x-flash-message type="success" :message="session('success')" />
  @endif

  {{-- TABLA --}}
  <div class="bg-gray-900 text-white rounded-xl shadow-2xl border-2 border-green-400 overflow-hidden">

    <div class="max-h-[65vh] overflow-y-auto">
      <table class="w-full text-sm text-left">
        <thead class="bg-gray-800 text-green-300 sticky top-0 z-20">
          <tr>
            <th class="px-6 py-3">ID</th>
            <th class="px-6 py-3">Código</th>
            <th class="px-6 py-3">Nombre</th>
            <th class="px-6 py-3">Email</th>
            <th class="px-6 py-3">Teléfono</th>
            <th class="px-6 py-3">Estado</th>
            <th class="px-6 py-3">Tipo</th>
            <th class="px-6 py-3 text-center">Acciones</th>
          </tr>
        </thead>

        <tbody id="clients-tbody" class="divide-y divide-gray-800">
          @include('clients._table', ['clients' => $clients])
        </tbody>
      </table>
    </div>

    <div id="clients-pagination" class="px-4 py-3 border-t border-gray-800">
      @include('clients._pagination', ['clients' => $clients])
    </div>

  </div>
</div>
@endsection
