{{-- resources/views/products/show.blade.php --}}
@extends('layout.admin')

@section('content')
<div class="w-full px-6">
  <h1 class="text-3xl font-bold mb-6 text-green-400 flex items-center gap-2">
    📦 Producto #{{ $product->id }}
  </h1>

  {{-- Flash global --}}
  <x-flash-message />

  <div class="bg-gray-900 text-white rounded-xl shadow-2xl p-8 border-2 border-green-400 w-full">

    @php
      $images   = $product->images ?? collect();
      $cover    = $product->coverImage ?? $images->first();
      $coverUrl = $cover? asset('storage/'.$cover->path) : asset('img/placeholder.png');
    @endphp

    {{-- Galería + Datos principales --}}
    <div class="grid md:grid-cols-2 gap-8">
      {{-- Galería --}}
      <div>
        {{-- Imagen principal (auto-ajuste sin recorte) --}}
        <div class="rounded-xl border border-gray-700 overflow-hidden bg-black/50">
          <div class="w-full h-80 sm:h-96 flex items-center justify-center">
            <img id="mainImage"
                 src="{{ $coverUrl }}"
                 alt="{{ $product->name }}"
                 class="max-h-full max-w-full object-contain cursor-zoom-in transition-transform"
                 data-idx="{{ $cover ? $images->search(fn($i)=>$i->id===$cover->id) : 0 }}">
          </div>
        </div>

        {{-- Miniaturas --}}
        @if($images->count())
          <div class="mt-3 grid grid-cols-4 sm:grid-cols-6 gap-2">
            @foreach($images as $i => $img)
              @php
                $thumbUrl = asset('storage/'.$img->path);
                $isActive = $cover && $cover->id === $img->id;
              @endphp
              <button type="button"
                      class="thumb-btn group relative rounded-lg overflow-hidden border {{ $isActive ? 'border-emerald-500 ring-2 ring-emerald-400' : 'border-gray-700' }}"
                      data-full="{{ $thumbUrl }}"
                      data-idx="{{ $i }}"
                      aria-label="Ver imagen">
                <div class="w-full h-20 bg-black/40 flex items-center justify-center">
                  <img src="{{ $thumbUrl }}" alt="{{ $img->alt ?? $product->name }}"
                       class="max-h-full max-w-full object-contain group-hover:opacity-90">
                </div>
              </button>
            @endforeach
          </div>
        @else
          <p class="text-gray-400 mt-3 text-sm">Sin imágenes cargadas.</p>
        @endif
      </div>

      {{-- Datos principales --}}
      <div class="text-lg">
        <p>
          <span class="font-semibold text-green-300">Código:</span>
          <span class="font-mono text-xl">{{ $product->code ?? '—' }}</span>
        </p>

        <p class="mt-2">
          <span class="font-semibold text-green-300">Nombre:</span>
          <span class="text-xl">{{ $product->name }}</span>
        </p>

        <p class="mt-2">
          <span class="font-semibold text-green-300">Marca:</span>
          {{ $product->brand->name ?? '—' }}
        </p>

        <p class="mt-2">
          <span class="font-semibold text-green-300">Categoría:</span>
          {{ $product->category->name ?? '—' }}
        </p>

        <p class="mt-2">
          <span class="font-semibold text-green-300">Proveedor:</span>
          {{ $product->supplier->name ?? '—' }}
        </p>

        <p class="mt-2">
          <span class="font-semibold text-green-300">💵 Precio contado:</span>
          <span class="text-xl">
            {{ $product->price_cash !== null ? money_py($product->price_cash) : '—' }}
          </span>
        </p>

        <p class="mt-2 flex items-center gap-2">
          <span class="font-semibold text-green-300">Stock:</span>
          <span class="px-3 py-1 rounded text-base font-bold
                {{ $product->stock > 0 ? 'bg-green-600' : 'bg-gray-600' }}">
            {{ $product->stock }}
          </span>
        </p>

        <p class="mt-2 flex items-center gap-2">
          <span class="font-semibold text-green-300">Activo:</span>
          <x-table-row-status :active="$product->active" />
        </p>

        {{-- Notas --}}
        <p class="mt-4">
          <span class="font-semibold text-green-300">Notas:</span>
          {{ $product->notes ?? '—' }}
        </p>

        <p class="mt-4 text-gray-400 text-base">
          📅 Creado: {{ $product->created_at?->format('d/m/Y H:i') }} ·
          🔄 Actualizado: {{ $product->updated_at?->format('d/m/Y H:i') }}
        </p>
      </div>
    </div>

    {{-- Precio en cuotas --}}
    <div class="mt-10">
      <h4 class="text-lg font-semibold text-green-400">Precios en cuotas</h4>

      @php
        $pis = $product->installments ?? collect();
      @endphp

      @if($pis->isEmpty())
        <p class="text-gray-400">—</p>
      @else
        <ul class="list-disc ml-6 text-gray-200">
          @foreach($pis as $pi)
            @php
              $n     = (int) $pi->installments;
              $cuota = (int) $pi->installment_price;
              $total = $n * $cuota;
            @endphp
            <li>
              {{ $n }} x @money($cuota)
              <span class="text-gray-400">(total: @money($total))</span>
            </li>
          @endforeach
        </ul>
      @endif
    </div>

    {{-- Acciones --}}
    <div class="flex flex-wrap gap-4 mt-10">
      <x-action-buttons
        :edit="route('products.edit', $product)"
        :delete="route('products.destroy', $product)"
        :name="'el producto '.$product->name" />

      <a href="{{ route('products.index') }}"
         class="px-6 py-2 text-sm rounded-lg border border-gray-500 text-gray-300 hover:bg-gray-600 font-semibold shadow">
        ← Volver
      </a>
    </div>

    {{-- Últimos movimientos del producto --}}
    @if(isset($movements) && $movements->count())
      <div class="mt-12">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-2xl font-semibold text-green-300">📦 Últimos movimientos</h2>
          <a href="{{ route('inventory.create', ['product_id' => $product->id]) }}"
             class="px-4 py-2 text-sm rounded-lg border border-emerald-500 text-emerald-400 hover:bg-emerald-500/10 font-semibold shadow">
            ➕ Nuevo movimiento
          </a>
        </div>

        <div class="overflow-x-auto rounded-lg border border-gray-700">
          <table class="min-w-full text-base text-left">
            <thead class="bg-gray-800 text-gray-200 uppercase text-xs tracking-wide">
              <tr>
                <th class="px-5 py-3">Fecha</th>
                <th class="px-5 py-3">Producto</th>
                <th class="px-5 py-3">Tipo</th>
                <th class="px-5 py-3">Cantidad</th>
                <th class="px-5 py-3">Razón</th>
                <th class="px-5 py-3">Usuario</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-700">
              @foreach($movements as $m)
                <tr class="hover:bg-gray-800/60 transition">
                  <td class="px-5 py-3 font-mono text-gray-200">{{ $m->created_at?->format('Y-m-d H:i') }}</td>
                  <td class="px-5 py-3 text-white">{{ $m->product->name ?? '—' }}</td>
                  <td class="px-5 py-3">
                    <span class="px-2 py-1 text-xs font-semibold rounded-full
                      {{ $m->type === 'entrada'
                        ? 'bg-emerald-600/30 text-emerald-300'
                        : 'bg-red-600/30 text-red-300' }}">
                      {{ ucfirst($m->type) }}
                    </span>
                  </td>
                  <td class="px-5 py-3 text-gray-300">{{ $m->quantity ?? $m->qty }}</td>
                  <td class="px-5 py-3 text-gray-400">{{ $m->reason ?? '—' }}</td>
                  <td class="px-5 py-3 text-gray-200">{{ $m->user->name ?? 'Sistema' }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    @else
      <p class="mt-10 text-gray-400 text-lg">No hay movimientos recientes para este producto.</p>
    @endif
  </div>
</div>
@endsection

@push('scripts')
<script>
  // Recolectar URLs de galería
  const gallery = Array.from(document.querySelectorAll('.thumb-btn')).map(btn => btn.getAttribute('data-full'));
  const mainImg = document.getElementById('mainImage');

  // Cambiar imagen principal desde miniaturas
  document.querySelectorAll('.thumb-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const full = btn.getAttribute('data-full');
      const idx  = parseInt(btn.getAttribute('data-idx') || '0', 10);
      if (full && mainImg) {
        mainImg.src = full;
        mainImg.dataset.idx = idx;
      }
      // estilos activos
      document.querySelectorAll('.thumb-btn').forEach(b => {
        b.classList.remove('ring-2','ring-emerald-400','border-emerald-500');
        b.classList.add('border-gray-700');
      });
      btn.classList.remove('border-gray-700');
      btn.classList.add('ring-2','ring-emerald-400','border-emerald-500');
    });
  });

  /* ============================
   * Lightbox / Modal de imagen
   * ============================ */
  function createLightbox() {
    const el = document.createElement('div');
    el.id = 'lightbox';
    el.className = 'fixed inset-0 z-50 hidden';
    el.innerHTML = `
      <div class="absolute inset-0 bg-black/80"></div>

      <div class="absolute inset-0 flex items-center justify-center p-4">
        <button id="lbPrev" class="mx-2 p-3 rounded-full bg-white/10 hover:bg-white/20 focus:outline-none" aria-label="Anterior">◀</button>

        <div class="relative max-w-6xl w-full">
          <img id="lbImg" src="" alt="Imagen" class="mx-auto max-h-[85vh] w-auto object-contain rounded shadow-2xl">
          <button id="lbClose" class="absolute -top-4 -right-4 p-2 rounded-full bg-red-600 hover:bg-red-700 text-white shadow" aria-label="Cerrar">✕</button>
        </div>

        <button id="lbNext" class="mx-2 p-3 rounded-full bg-white/10 hover:bg-white/20 focus:outline-none" aria-label="Siguiente">▶</button>
      </div>
    `;
    document.body.appendChild(el);
    return el;
  }

  const lb = createLightbox();
  const lbImg   = lb.querySelector('#lbImg');
  const lbClose = lb.querySelector('#lbClose');
  const lbPrev  = lb.querySelector('#lbPrev');
  const lbNext  = lb.querySelector('#lbNext');

  let current = 0;

  function openLightbox(idx = 0) {
    if (!gallery.length) return;
    current = Math.max(0, Math.min(idx, gallery.length - 1));
    lbImg.src = gallery[current] || mainImg.src;
    lb.classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
  }

  function closeLightbox() {
    lb.classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
  }

  function prev() {
    if (!gallery.length) return;
    current = (current - 1 + gallery.length) % gallery.length;
    lbImg.src = gallery[current];
  }

  function next() {
    if (!gallery.length) return;
    current = (current + 1) % gallery.length;
    lbImg.src = gallery[current];
  }

  // Abrir con click en la imagen principal
  if (mainImg) {
    mainImg.addEventListener('click', () => {
      const idx = parseInt(mainImg.dataset.idx || '0', 10);
      openLightbox(idx);
    });
  }

  // Cerrar / navegar
  lbClose.addEventListener('click', closeLightbox);
  lb.addEventListener('click', (e) => {
    // cerrar al clickear fondo oscuro
    if (e.target === lb || e.target.classList.contains('bg-black/80')) closeLightbox();
  });
  lbPrev.addEventListener('click', prev);
  lbNext.addEventListener('click', next);

  // Teclado
  document.addEventListener('keydown', (e) => {
    if (lb.classList.contains('hidden')) return;
    if (e.key === 'Escape') closeLightbox();
    if (e.key === 'ArrowLeft') prev();
    if (e.key === 'ArrowRight') next();
  });
</script>
@endpush
