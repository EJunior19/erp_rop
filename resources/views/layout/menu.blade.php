{{-- Sidebar (Tailwind + Alpine) --}}
<aside id="sidebar"
       class="w-64 bg-gray-900 text-gray-200 flex flex-col transition-transform duration-300 transform
              fixed inset-y-0 left-0 z-50 md:translate-x-0 md:static overflow-y-auto"
       :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
       x-data="{
         openCatalogo: {{ request()->routeIs('products.*','categories.*','brands.*','suppliers.*','inventory.*','catalog.*','ecommerce.*','vista-ecommerce') ? 'true' : 'false' }},
         openReportes: {{ request()->routeIs('reports.*') ? 'true' : 'false' }},
         openCompras:  {{ request()->routeIs(
                            'purchase_orders.*',
                            'purchase_receipts.*',
                            'purchase_invoices.*',
                            'purchases.*',
                            'payables.*'
                        ) ? 'true' : 'false' }}
       }">

  {{-- Header --}}
  <div class="px-6 py-4 text-lg font-bold border-b border-gray-700 flex items-center gap-2">
    <i class="fa-solid fa-cubes text-indigo-400"></i>
    <span>CRM Katuete</span>
  </div>

  {{-- MENU --}}
  <nav class="flex-1 px-3 py-4 space-y-2 overflow-y-auto scrollbar-thin scrollbar-thumb-gray-700">

    {{-- PRINCIPAL --}}
    <h6 class="text-xs uppercase tracking-wider text-gray-400 px-2">Principal</h6>

    <a href="{{ route('dashboard.index') }}"
       class="flex items-center gap-3 px-3 py-2 rounded-md hover:bg-gray-800 transition
              {{ request()->routeIs('dashboard.*') ? 'bg-gray-800 text-white' : '' }}">
      <i class="fas fa-gauge w-5 text-blue-400"></i>
      <span>Panel</span>
    </a>

    <a href="{{ route('clients.index') }}"
       class="flex items-center gap-3 px-3 py-2 rounded-md hover:bg-gray-800 transition
              {{ request()->routeIs('clients.*') ? 'bg-gray-800 text-white' : '' }}">
      <i class="fa-solid fa-users w-5 text-green-400"></i>
      <span>Clientes</span>
    </a>

    <a href="{{ route('sales.index') }}"
       class="flex items-center gap-3 px-3 py-2 rounded-md hover:bg-gray-800 transition
              {{ request()->routeIs('sales.*') ? 'bg-gray-800 text-white' : '' }}">
      <i class="fa-solid fa-receipt w-5 text-emerald-400"></i>
      <span>Ventas</span>
    </a>

    <a href="{{ route('credits.index') }}"
       class="flex items-center gap-3 px-3 py-2 rounded-md hover:bg-gray-800 transition
              {{ request()->routeIs('credits.*') ? 'bg-gray-800 text-white' : '' }}">
      <i class="fa-solid fa-hand-holding-dollar w-5 text-emerald-400"></i>
      <span>Cuentas por Cobrar</span>
    </a>

    {{-- CUENTAS POR PAGAR --}}
    <a href="{{ route('payables.index') }}"
       class="flex items-center gap-3 px-3 py-2 rounded-md hover:bg-gray-800 transition
              {{ request()->routeIs('payables.*') ? 'bg-gray-800 text-white' : '' }}">
      <i class="fa-solid fa-file-invoice-dollar w-5 text-red-400"></i>
      <span>Cuentas por Pagar</span>
    </a>

    {{-- DEBUG LOCAL --}}
    @env('local')
      <div class="px-3 py-1 text-[11px] text-amber-300">
        uid: {{ Auth::id() }} |
        role_id: {{ Auth::user()->role_id }} |
        can:view-finance: {{ Auth::user()?->can('view-finance') ? 'YES' : 'NO' }}
      </div>
    @endenv

    {{-- FINANZAS (con PIN) --}}
    @php
      $isFinance = request()->routeIs('finance.*');
      $pinOk = session('finance_pin_ok', false);
    @endphp

    @can('view-finance')
      <a href="{{ $pinOk ? route('finance.index') : route('finance.pin') }}"
         class="flex items-center gap-3 px-3 py-2 rounded-md hover:bg-gray-800 transition
                {{ $isFinance ? 'bg-gray-800 text-white' : '' }}">
        <i class="fa-solid fa-sack-dollar w-5 {{ $pinOk ? 'text-emerald-400' : 'text-amber-400' }}"></i>
        <span class="flex items-center gap-2">
          Finanzas
          <span class="text-xs px-1.5 py-0.5 rounded
                       {{ $pinOk ? 'bg-emerald-900/40 text-emerald-300 border border-emerald-700/50'
                                 : 'bg-amber-900/30 text-amber-300 border border-amber-700/50' }}">
            {{ $pinOk ? '🔓 PIN OK' : '🔒 PIN' }}
          </span>
        </span>
      </a>
    @endcan

    {{-- COMPRAS --}}
    <h6 class="text-xs uppercase tracking-wider text-gray-400 px-2 mt-4">Compras</h6>

    <button @click="openCompras = !openCompras"
            class="w-full flex items-center justify-between px-3 py-2 rounded-md hover:bg-gray-800 transition">
      <div class="flex items-center gap-3">
        <i class="fa-solid fa-cart-shopping w-5 text-sky-400"></i>
        <span>Compras</span>
      </div>
      <i class="fas fa-angle-down transition-transform duration-300"
         :class="openCompras ? 'rotate-180' : ''"></i>
    </button>

    <div x-show="openCompras" x-collapse class="ml-6 mt-2 space-y-1">

      <a href="{{ route('purchase_orders.index') }}"
         class="flex items-center gap-3 px-3 py-2 rounded-md hover:bg-gray-800 transition
                {{ request()->routeIs('purchase_orders.*') ? 'bg-gray-800 text-white' : '' }}">
        <i class="fa-solid fa-file-circle-plus w-5 text-sky-400"></i>
        <span>Órdenes de compra</span>
      </a>

      <a href="{{ route('purchase_receipts.index') }}"
         class="flex items-center gap-3 px-3 py-2 rounded-md hover:bg-gray-800 transition
                {{ request()->routeIs('purchase_receipts.*') ? 'bg-gray-800 text-white' : '' }}">
        <i class="fa-solid fa-truck-ramp-box w-5 text-sky-400"></i>
        <span>Recepciones</span>
      </a>

      <a href="{{ route('purchase_invoices.index') }}"
         class="flex items-center gap-3 px-3 py-2 rounded-md hover:bg-gray-800 transition
                {{ request()->routeIs('purchase_invoices.*') ? 'bg-gray-800 text-white' : '' }}">
        <i class="fa-solid fa-file-invoice w-5 text-sky-400"></i>
        <span>Facturas de compra</span>
      </a>

      @if(Route::has('purchases.index'))
        <a href="{{ route('purchases.index') }}"
           class="flex items-center gap-3 px-3 py-2 rounded-md hover:bg-gray-800 transition
                  {{ request()->routeIs('purchases.*') ? 'bg-gray-800 text-white' : '' }}">
          <i class="fa-solid fa-cart-flatbed w-5 text-sky-400"></i>
          <span>Compras (legacy)</span>
        </a>
      @endif
    </div>

  
    {{-- CATÁLOGO --}}
    <h6 class="text-xs uppercase tracking-wider text-gray-400 px-2 mt-4">Catálogo</h6>

    <button @click="openCatalogo = !openCatalogo"
            class="w-full flex items-center justify-between px-3 py-2 rounded-md hover:bg-gray-800 transition">
      <div class="flex items-center gap-3">
        <i class="fa-solid fa-boxes-stacked w-5 text-yellow-400"></i>
        <span>Catálogo</span>
      </div>
      <i class="fas fa-angle-down transition-transform duration-300"
         :class="openCatalogo ? 'rotate-180' : ''"></i>
    </button>

    <div x-show="openCatalogo" x-collapse class="ml-6 mt-2 space-y-1">

     
      <a href="{{ route('products.index') }}"
         class="flex items-center gap-3 px-3 py-2 rounded-md hover:bg-gray-800 transition
                {{ request()->routeIs('products.*') ? 'bg-gray-800 text-white' : '' }}">
        <i class="fa-solid fa-box w-5 text-blue-400"></i>
        <span>Productos</span>
      </a>

      <a href="{{ route('categories.index') }}"
         class="flex items-center gap-3 px-3 py-2 rounded-md hover:bg-gray-800 transition
                {{ request()->routeIs('categories.*') ? 'bg-gray-800 text-white' : '' }}">
        <i class="fa-solid fa-tags w-5 text-pink-400"></i>
        <span>Categorías</span>
      </a>

      <a href="{{ route('brands.index') }}"
         class="flex items-center gap-3 px-3 py-2 rounded-md hover:bg-gray-800 transition
                {{ request()->routeIs('brands.*') ? 'bg-gray-800 text-white' : '' }}">
        <i class="fa-solid fa-registered w-5 text-indigo-400"></i>
        <span>Marcas</span>
      </a>

      <a href="{{ route('suppliers.index') }}"
         class="flex items-center gap-3 px-3 py-2 rounded-md hover:bg-gray-800 transition
                {{ request()->routeIs('suppliers.*') ? 'bg-gray-800 text-white' : '' }}">
        <i class="fa-solid fa-truck-field w-5 text-orange-400"></i>
        <span>Proveedores</span>
      </a>

      <a href="{{ route('inventory.index') }}"
         class="flex items-center gap-3 px-3 py-2 rounded-md hover:bg-gray-800 transition
                {{ request()->routeIs('inventory.*') ? 'bg-gray-800 text-white' : '' }}">
        <i class="fa-solid fa-warehouse w-5 text-purple-400"></i>
        <span>Inventario</span>
      </a>

    </div>

    {{-- REPORTES --}}
    <h6 class="text-xs uppercase tracking-wider text-gray-400 px-2 mt-4">Reportes</h6>

    <button @click="openReportes = !openReportes"
            class="w-full flex items-center justify-between px-3 py-2 rounded-md hover:bg-gray-800 transition">
      <div class="flex items-center gap-3">
        <i class="fa-solid fa-chart-line w-5 text-emerald-400"></i>
        <span>Reportes</span>
      </div>
      <i class="fas fa-angle-down transition-transform duration-300"
         :class="openReportes ? 'rotate-180' : ''"></i>
    </button>

    <div x-show="openReportes" x-collapse class="ml-6 mt-2 space-y-1">
      <a href="{{ route('reports.sales') }}"
         class="flex items-center gap-3 px-3 py-2 rounded-md hover:bg-gray-800 transition
                {{ request()->routeIs('reports.sales') ? 'bg-gray-800 text-white' : '' }}">
        <i class="fa-solid fa-file-invoice-dollar w-5 text-emerald-400"></i>
        <span>Ventas</span>
      </a>

      <a href="{{ route('reports.purchases') }}"
         class="flex items-center gap-3 px-3 py-2 rounded-md hover:bg-gray-800 transition
                {{ request()->routeIs('reports.purchases') ? 'bg-gray-800 text-white' : '' }}">
        <i class="fa-solid fa-file-invoice w-5 text-sky-400"></i>
        <span>Compras</span>
      </a>

      <a href="{{ route('reports.credits') }}"
         class="flex items-center gap-3 px-3 py-2 rounded-md hover:bg-gray-800 transition
                {{ request()->routeIs('reports.credits') ? 'bg-gray-800 text-white' : '' }}">
        <i class="fa-solid fa-hand-holding-dollar w-5 text-amber-400"></i>
        <span>Cuentas por cobrar</span>
      </a>

      <a href="{{ route('reports.inventory') }}"
         class="flex items-center gap-3 px-3 py-2 rounded-md hover:bg-gray-800 transition
                {{ request()->routeIs('reports.inventory') ? 'bg-gray-800 text-white' : '' }}">
        <i class="fa-solid fa-box-open w-5 text-purple-400"></i>
        <span>Inventario</span>
      </a>
    </div>

  </nav>

  {{-- FOOTER --}}
  <div class="px-6 py-4 border-t border-gray-700 text-sm text-gray-400">
    <div class="mb-1">Conectado como</div>
    <div class="font-medium text-gray-200 flex items-center gap-2">
      <i class="fa-solid fa-circle-user text-indigo-400"></i>
      {{ Auth::user()->name ?? 'CRM Katuete' }}
    </div>
  </div>

</aside>
