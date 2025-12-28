{{-- resources/views/products/show.blade.php --}}
@extends('layout.admin')

@section('content')
<div class="w-full px-6">
  <h1 class="text-3xl font-bold mb-6 text-emerald-400 flex items-center gap-2">
    📦 Producto #{{ $product->id }}
  </h1>

  <x-flash-message />

  <div class="bg-slate-900 text-slate-100 rounded-xl shadow-2xl p-8 border border-slate-700 w-full">

    @php
      use Illuminate\Support\Facades\Storage;
      use Illuminate\Support\Str;

      $images = ($product->relationLoaded('images') ? $product->images : $product->images()->get()) ?? collect();
      $cover  = $product->relationLoaded('coverImage') ? $product->coverImage : $product->coverImage()->first();
      if (!$cover) { $cover = $images->first(); }

      $toPublicUrl = function ($path) {
        if (!$path) return null;
        $clean = ltrim(Str::of($path)->replaceFirst('public/', ''), '/');
        return '/storage/'.$clean;
      };

      $coverUrl = $cover ? $toPublicUrl($cover->path) : asset('img/placeholder.png');
    @endphp

    {{-- Galería + Datos --}}
    <div class="grid md:grid-cols-2 gap-8">

      {{-- Galería --}}
      <div>
        <div class="rounded-xl border border-slate-700 bg-black/40">
          <div class="w-full h-80 sm:h-96 flex items-center justify-center">
            <img id="mainImage"
                 src="{{ $coverUrl }}"
                 alt="{{ $cover->alt ?? $product->name }}"
                 class="max-h-full max-w-full object-contain cursor-zoom-in transition"
                 data-idx="{{ $cover ? $images->search(fn($i)=>$i->id===$cover->id) : 0 }}">
          </div>
        </div>

        @if($images->count())
          <div class="mt-3 grid grid-cols-4 sm:grid-cols-6 gap-2">
            @foreach($images as $i => $img)
              @php
                $thumbUrl = $toPublicUrl($img->path);
                $isActive = $cover && $cover->id === $img->id;
              @endphp
              <button type="button"
                class="thumb-btn rounded-lg border {{ $isActive ? 'border-emerald-500 ring-2 ring-emerald-400' : 'border-slate-700' }}"
                data-full="{{ $thumbUrl }}"
                data-idx="{{ $i }}">
                <div class="w-full h-20 bg-black/40 flex items-center justify-center">
                  <img src="{{ $thumbUrl }}" class="max-h-full max-w-full object-contain">
                </div>
              </button>
            @endforeach
          </div>
        @else
          <p class="text-slate-400 mt-3 text-sm">Sin imágenes cargadas.</p>
        @endif
      </div>

      {{-- Datos --}}
      <div class="space-y-2 text-lg">
        <p><span class="text-slate-400">Código:</span> <span class="font-mono text-xl">{{ $product->code ?? '—' }}</span></p>
        <p><span class="text-slate-400">Nombre:</span> <span class="text-xl font-medium">{{ $product->name }}</span></p>
        <p><span class="text-slate-400">Marca:</span> {{ $product->brand->name ?? '—' }}</p>
        <p><span class="text-slate-400">Categoría:</span> {{ $product->category->name ?? '—' }}</p>
        <p><span class="text-slate-400">Proveedor:</span> {{ $product->supplier->name ?? '—' }}</p>

        <p class="pt-2">
          <span class="text-slate-400">💵 Precio contado:</span>
          <span class="text-emerald-400 font-semibold text-xl">
            {{ $product->price_cash !== null ? money_py($product->price_cash) : '—' }}
          </span>
        </p>

        <p class="flex items-center gap-2 pt-2">
          <span class="text-slate-400">Stock:</span>
          <span class="inline-flex items-center justify-center w-9 h-9 rounded-full font-bold
            {{ $product->stock > 0 ? 'bg-emerald-500 text-emerald-950' : 'bg-red-600 text-red-50' }}">
            {{ $product->stock }}
          </span>
        </p>

        <p class="flex items-center gap-2">
          <span class="text-slate-400">Activo:</span>
          <x-table-row-status :active="$product->active" />
        </p>

        <p class="pt-4">
          <span class="text-slate-400">Notas:</span>
          {{ $product->notes ?? '—' }}
        </p>

        <p class="text-slate-500 text-sm pt-4">
          📅 Creado: {{ $product->created_at?->format('d/m/Y H:i') }} ·
          🔄 Actualizado: {{ $product->updated_at?->format('d/m/Y H:i') }}
        </p>
      </div>
    </div>

    {{-- Precios en cuotas --}}
    <div class="mt-10">
      <h4 class="text-lg font-semibold text-emerald-400">💳 Precios en cuotas</h4>
      @php $pis = $product->installments ?? collect(); @endphp

      @if($pis->isEmpty())
        <p class="text-slate-400">—</p>
      @else
        <ul class="list-disc ml-6 text-slate-200">
          @foreach($pis as $pi)
            @php
              $n = (int)$pi->installments;
              $cuota = (int)$pi->installment_price;
            @endphp
            <li>{{ $n }} x @money($cuota)</li>
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
        class="px-4 py-2 border border-slate-600 text-slate-300 rounded hover:bg-slate-800 text-sm">
        ← Volver
      </a>
    </div>

    {{-- Movimientos --}}
    @if(isset($movements) && $movements->count())
      <div class="mt-12">
        <h2 class="text-2xl font-semibold text-emerald-300 mb-4">📦 Últimos movimientos</h2>

        <div class="overflow-x-auto rounded-lg border border-slate-700">
          <table class="min-w-full text-sm">
            <thead class="bg-slate-800 text-slate-300 uppercase text-xs">
              <tr>
                <th class="px-5 py-3">Fecha</th>
                <th class="px-5 py-3">Tipo</th>
                <th class="px-5 py-3">Cantidad</th>
                <th class="px-5 py-3">Razón</th>
                <th class="px-5 py-3">Usuario</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-700">
              @foreach($movements as $m)
                <tr class="hover:bg-slate-800/50">
                  <td class="px-5 py-3 font-mono">{{ $m->created_at?->format('Y-m-d H:i') }}</td>
                  <td class="px-5 py-3">
                    <span class="px-2 py-1 rounded-full text-xs font-semibold
                      {{ $m->type === 'entrada' ? 'bg-emerald-600/30 text-emerald-300' : 'bg-red-600/30 text-red-300' }}">
                      {{ ucfirst($m->type) }}
                    </span>
                  </td>
                  <td class="px-5 py-3">{{ $m->quantity ?? $m->qty }}</td>
                  <td class="px-5 py-3 text-slate-400">{{ $m->reason ?? '—' }}</td>
                  <td class="px-5 py-3">{{ $m->user->name ?? 'Sistema' }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
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
    lbImg.src = gallery[current] || (document.getElementById('mainImage')?.src ?? '');
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
  const mainImageEl = document.getElementById('mainImage');
  if (mainImageEl) {
    mainImageEl.addEventListener('click', () => {
      const idx = parseInt(mainImageEl.dataset.idx || '0', 10);
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
