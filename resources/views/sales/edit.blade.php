{{-- resources/views/sales/edit.blade.php --}}
@extends('layout.admin')

@section('content')
@php
  // ✅ Compatibilidad: si tu columna es "estado", esto evita romper
  $currentStatus = $sale->status ?? $sale->estado ?? 'pendiente_aprobacion';

  // helper rápido para miles con punto (sin decimales)
  $nf0 = fn($n) => number_format((float)($n ?? 0), 0, ',', '.');
@endphp

<h1 class="text-2xl font-bold text-white mb-6 tracking-wide">
  ✏️ Editar Venta #{{ $sale->id }}
</h1>

{{-- ⚠️ Mostrar errores --}}
@if ($errors->any())
  <div class="mb-4 p-3 bg-red-100 text-red-700 rounded-lg border border-red-400">
    <ul class="list-disc ml-5">
      @foreach ($errors->all() as $e)
        <li>{{ $e }}</li>
      @endforeach
    </ul>
  </div>
@endif

<form method="POST" action="{{ route('sales.update',$sale) }}" id="sale-form"
      class="bg-gray-950 text-white rounded-xl shadow-lg p-8 space-y-6 border border-gray-800/50">
  @csrf
  @method('PUT')

  {{-- ================= CABECERA ================= --}}
  <div class="grid md:grid-cols-3 gap-6">
    {{-- Cliente --}}
    <div>
      <label class="block text-xs font-semibold uppercase text-gray-400 mb-1">Cliente</label>
      <input type="text" value="{{ $sale->client->name }} (ID:{{ $sale->client_id }})"
             class="w-full rounded-lg bg-gray-900 border border-gray-700 text-white px-3 py-2" disabled>
      <input type="hidden" name="client_id" value="{{ $sale->client_id }}">
    </div>

    {{-- Modo de pago --}}
    <div>
      <label class="block text-xs font-semibold uppercase text-gray-400 mb-1">Modo de pago</label>
      <select name="modo_pago" id="modo_pago"
              class="w-full rounded-lg bg-gray-900 border border-gray-700 text-white px-3 py-2">
        <option value="contado" {{ $sale->modo_pago==='contado'?'selected':'' }}>Contado</option>
        <option value="credito" {{ $sale->modo_pago==='credito'?'selected':'' }}>Crédito</option>
      </select>
    </div>

    {{-- Fecha --}}
    <div>
      <label class="block text-xs font-semibold uppercase text-gray-400 mb-1">Fecha</label>
      <input type="date" name="fecha" value="{{ $sale->fecha?->format('Y-m-d') }}"
             class="w-full rounded-lg bg-gray-900 border border-gray-700 text-white px-3 py-2"/>
    </div>
  </div>

  {{-- ✅ Estado (AHORA ES status) --}}
  <div>
    <label class="block text-xs font-semibold uppercase text-gray-400 mb-1">Estado</label>

    <select name="status"
            class="w-full rounded-lg bg-gray-900 border border-gray-700 text-white px-3 py-2">
      <option value="pendiente_aprobacion" {{ $currentStatus==='pendiente_aprobacion'?'selected':'' }}>Pendiente aprobación</option>
      <option value="aprobado"            {{ $currentStatus==='aprobado'?'selected':'' }}>Aprobado</option>
      <option value="rechazado"           {{ $currentStatus==='rechazado'?'selected':'' }}>Rechazado</option>
      <option value="editable"            {{ $currentStatus==='editable'?'selected':'' }}>Editable</option>
      <option value="cancelado"           {{ $currentStatus==='cancelado'?'selected':'' }}>Cancelado</option>
    </select>

    {{-- ✅ Por si el backend viejo todavía espera "estado" --}}
    <input type="hidden" name="estado" value="{{ $currentStatus }}">
  </div>

  {{-- ================= BLOQUE CREDITO ================= --}}
  @php
    $credit = $sale->credit ?? null;

    $defaultFirstDue = null;
    if ($credit?->due_date) $defaultFirstDue = \Carbon\Carbon::parse($credit->due_date)->format('Y-m-d');
    if (!$defaultFirstDue) $defaultFirstDue = now()->addDays(30)->format('Y-m-d');

    $defaultNotify = $credit?->notify_every_days ?? 7;
  @endphp

  <div id="credit-box" class="rounded-xl border border-amber-500/30 bg-amber-900/10 p-5 space-y-4">
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-semibold text-amber-300 tracking-wide">💳 Datos de crédito</h2>
      <span class="text-xs text-gray-400">Se guarda solo si el modo de pago es Crédito</span>
    </div>

    <div class="grid md:grid-cols-4 gap-4">
      <div>
        <label class="block text-xs font-semibold uppercase text-gray-400 mb-1">Cuotas</label>
        <input type="number" min="1" name="credito[cuotas]" id="credito_cuotas"
               value="{{ old('credito.cuotas', $credit->installments ?? 3) }}"
               class="w-full rounded-lg bg-gray-900 border border-gray-700 text-white px-3 py-2">
      </div>

      <div>
        <label class="block text-xs font-semibold uppercase text-gray-400 mb-1">Entrega inicial</label>
        <input type="number" min="0" step="0.01" name="credito[entrega_inicial]" id="credito_entrega"
               value="{{ old('credito.entrega_inicial', 0) }}"
               class="w-full rounded-lg bg-gray-900 border border-gray-700 text-white px-3 py-2">
      </div>

      <div>
        <label class="block text-xs font-semibold uppercase text-gray-400 mb-1">Primer vencimiento</label>
        <input type="date" name="credito[primer_vencimiento]" id="credito_primer_venc"
               value="{{ old('credito.primer_vencimiento', $defaultFirstDue) }}"
               class="w-full rounded-lg bg-gray-900 border border-gray-700 text-white px-3 py-2">
      </div>

      <div>
        <label class="block text-xs font-semibold uppercase text-gray-400 mb-1">Cada (días)</label>
        <input type="number" min="1" name="credito[cada_dias]" id="credito_cada_dias"
               value="{{ old('credito.cada_dias', 30) }}"
               class="w-full rounded-lg bg-gray-900 border border-gray-700 text-white px-3 py-2">
      </div>
    </div>

    <div class="grid md:grid-cols-3 gap-4">
      <div>
        <label class="block text-xs font-semibold uppercase text-gray-400 mb-1">Monto por cuota</label>
        <input type="number" min="0" step="0.01" name="credito[monto_cuota]" id="credito_monto_cuota"
               value="{{ old('credito.monto_cuota', 0) }}"
               class="w-full rounded-lg bg-gray-900 border border-gray-700 text-white px-3 py-2">
        <p class="text-xs text-gray-500 mt-1">Si lo dejás 0, se recalcula automáticamente.</p>
      </div>

      <div>
        <label class="block text-xs font-semibold uppercase text-gray-400 mb-1">Total crédito</label>
        <input type="number" min="0" step="0.01" name="credito[total_credito]" id="credito_total_credito"
               value="{{ old('credito.total_credito', 0) }}"
               class="w-full rounded-lg bg-gray-900 border border-gray-700 text-white px-3 py-2">
      </div>

      <div>
        <label class="block text-xs font-semibold uppercase text-gray-400 mb-1">Notificar cada (días)</label>
        <input type="number" min="1" name="credito[notify_every_days]" id="credito_notify_days"
               value="{{ old('credito.notify_every_days', $defaultNotify) }}"
               class="w-full rounded-lg bg-gray-900 border border-gray-700 text-white px-3 py-2">
      </div>
    </div>

    <div class="text-xs text-gray-400">
      💡 Tip: si cambiás ítems o total, podés tocar “Cuotas” y se recalcula el monto por cuota.
    </div>
  </div>

  {{-- ================= ÍTEMS ================= --}}
  <div class="space-y-3">
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-semibold text-white tracking-wide">Ítems</h2>
      <button type="button" id="add-row"
              class="px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-medium shadow-md shadow-blue-500/30">
        ➕ Agregar ítem
      </button>
    </div>

    <div class="overflow-x-visible">
      <table class="table-fixed w-full text-sm border border-gray-800/50 rounded-lg overflow-hidden shadow">
        <thead>
          <tr class="text-left text-gray-400 bg-gray-800/60 uppercase tracking-wider text-xs">
            <th class="px-3 py-2">ID</th>
            <th class="px-3 py-2">Nombre</th>
            <th class="px-3 py-2">Precio</th>
            <th class="px-3 py-2">Cant.</th>
            <th class="px-3 py-2">IVA</th>
            <th class="px-3 py-2 text-right">Subtotal</th>
            <th class="px-3 py-2 text-right">—</th>
          </tr>
        </thead>
        <tbody id="items-body" class="divide-y divide-gray-700">
          @foreach($sale->items as $it)
            @php
              $lineTotal = ($it->line_total ?? (($it->unit_price ?? 0) * ($it->qty ?? 0)));
            @endphp
            <tr class="text-gray-200">
              <td class="px-3 py-2">
                <input type="text" class="pid w-20 rounded-lg bg-gray-900 border border-gray-700 px-2 py-1"
                       value="{{ $it->product_id }}" placeholder="ID">
              </td>
              <td class="px-3 py-2 relative">
                <input type="text" class="pname w-64 rounded-lg bg-gray-900 border border-gray-700 px-2 py-1"
                       value="{{ $it->product_name }}" placeholder="Nombre o buscar…">
                <div class="suggest absolute z-20 mt-1 w-80 max-h-64 overflow-auto rounded-lg border border-gray-700 bg-gray-900 text-gray-100 shadow hidden"></div>
              </td>
              <td class="px-3 py-2">
                <input type="number" step="0.01" min="0" class="price w-28 text-right rounded-lg bg-gray-900 border border-gray-700 px-2 py-1"
                       value="{{ $it->unit_price }}">
              </td>
              <td class="px-3 py-2">
                <input type="number" min="1" class="qty w-20 text-right rounded-lg bg-gray-900 border border-gray-700 px-2 py-1"
                       value="{{ $it->qty }}">
              </td>
              <td class="px-3 py-2">
                <select class="iva w-28 rounded-lg bg-gray-900 border border-gray-700 px-2 py-1">
                  <option value="10" {{ $it->iva_type==='10'?'selected':'' }}>IVA 10%</option>
                  <option value="5" {{ $it->iva_type==='5'?'selected':'' }}>IVA 5%</option>
                  <option value="exento" {{ $it->iva_type==='exento'?'selected':'' }}>Exento</option>
                </select>
              </td>
              <td class="px-3 py-2 text-right">
                <span class="subtotal">Gs. {{ $nf0($lineTotal) }}</span>
              </td>
              <td class="px-3 py-2 text-right">
                <button type="button" class="del px-2 py-1 rounded-lg border border-red-600/40 text-red-400 hover:bg-red-900/30">
                  ✕
                </button>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>

  {{-- ================= NOTA ================= --}}
  <div>
    <label class="block text-xs font-semibold uppercase text-gray-400 mb-1">Nota</label>
    <textarea name="nota" rows="3"
      class="w-full rounded-lg bg-gray-900 border border-gray-700 text-white px-3 py-2">{{ $sale->nota }}</textarea>
  </div>

  {{-- ================= TOTALES ================= --}}
  <div class="grid md:grid-cols-2 gap-4">
    <div></div>
    <div class="rounded-xl border border-gray-800 bg-gray-900/80 p-5 text-gray-300 shadow-lg">
      <div class="flex justify-between"><span>Gravada 10%:</span><span id="grav10">Gs. 0</span></div>
      <div class="flex justify-between"><span>IVA 10%:</span><span id="iva10">Gs. 0</span></div>
      <div class="flex justify-between"><span>Gravada 5%:</span><span id="grav5">Gs. 0</span></div>
      <div class="flex justify-between"><span>IVA 5%:</span><span id="iva5">Gs. 0</span></div>
      <div class="flex justify-between"><span>Exento:</span><span id="exento">Gs. 0</span></div>
      <div class="border-t border-gray-700 my-2"></div>
      <div class="flex justify-between font-semibold text-white text-lg"><span>Total IVA:</span><span id="totIva">Gs. 0</span></div>
      <div class="flex justify-between text-2xl font-bold text-blue-400">
        <span>Total:</span>
        <span id="totGen">Gs. {{ $nf0($sale->total) }}</span>
      </div>
    </div>
  </div>

  {{-- ================= ACCIONES ================= --}}
  <div class="flex gap-3">
    <button class="px-5 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-semibold shadow-lg shadow-blue-500/30">
      💾 Actualizar
    </button>
    <a href="{{ route('sales.index') }}"
       class="px-5 py-2 rounded-lg bg-gray-800 hover:bg-gray-700 border border-gray-600 text-gray-300 font-medium">
      Cancelar
    </a>
  </div>
</form>

{{-- ================= TEMPLATE FILA ================= --}}
<template id="row-template">
  <tr class="text-gray-200">
    <td class="px-3 py-2"><input type="text" class="pid w-20 rounded-lg bg-gray-900 border border-gray-700 px-2 py-1" placeholder="ID"></td>
    <td class="px-3 py-2 relative">
      <input type="text" class="pname w-64 rounded-lg bg-gray-900 border border-gray-700 px-2 py-1" placeholder="Nombre o buscar…">
      <div class="suggest absolute z-20 mt-1 w-80 max-h-64 overflow-auto rounded-lg border border-gray-700 bg-gray-900 text-gray-100 shadow hidden"></div>
    </td>
    <td class="px-3 py-2"><input type="number" step="0.01" min="0" class="price w-28 text-right rounded-lg bg-gray-900 border border-gray-700 px-2 py-1" value="0"></td>
    <td class="px-3 py-2"><input type="number" min="1" class="qty w-20 text-right rounded-lg bg-gray-900 border border-gray-700 px-2 py-1" value="1"></td>
    <td class="px-3 py-2">
      <select class="iva w-28 rounded-lg bg-gray-900 border border-gray-700 px-2 py-1">
        <option value="10">IVA 10%</option>
        <option value="5">IVA 5%</option>
        <option value="exento">Exento</option>
      </select>
    </td>
    <td class="px-3 py-2 text-right"><span class="subtotal">Gs. 0</span></td>
    <td class="px-3 py-2 text-right"><button type="button" class="del px-2 py-1 rounded-lg border border-red-600/40 text-red-400 hover:bg-red-900/30">✕</button></td>
  </tr>
</template>

@php
  $productSearchUrl = route('products.search');
  $productFindUrl = url('/api/products');
@endphp

<script>
(function(){
  // ✅ miles con punto: es-PY
  const fmt = n => 'Gs. ' + (Math.round(n).toLocaleString('es-PY'));

  const debounce = (fn,ms=250)=>{ let t; return(...a)=>{ clearTimeout(t); t=setTimeout(()=>fn(...a),ms); }; };

  const body=document.getElementById('items-body');
  const tpl=document.getElementById('row-template');
  const add=document.getElementById('add-row');

  const modoPagoSel = document.getElementById('modo_pago');
  const creditBox   = document.getElementById('credit-box');

  const cCuotas = document.getElementById('credito_cuotas');
  const cEntrega= document.getElementById('credito_entrega');
  const cMonto  = document.getElementById('credito_monto_cuota');
  const cTotal  = document.getElementById('credito_total_credito');

  function showHideCredit(){
    const isCred = (modoPagoSel.value === 'credito');
    creditBox.style.display = isCred ? '' : 'none';
  }

  function recalc(){
    let g10=0,i10=0,g5=0,i5=0,ex=0;

    body.querySelectorAll('tr').forEach(tr=>{
      const price=+tr.querySelector('.price').value||0;
      const qty=+tr.querySelector('.qty').value||0;
      const iva=tr.querySelector('.iva').value;

      const line=price*qty;
      tr.querySelector('.subtotal').textContent=fmt(line);

      if(iva==='10'){ let grav=line/1.1; g10+=grav; i10+=line-grav; }
      else if(iva==='5'){ let grav=line/1.05; g5+=grav; i5+=line-grav; }
      else ex+=line;
    });

    const totIva=i10+i5, total=g10+g5+ex+totIva;

    document.getElementById('grav10').textContent=fmt(g10);
    document.getElementById('iva10').textContent=fmt(i10);
    document.getElementById('grav5').textContent=fmt(g5);
    document.getElementById('iva5').textContent=fmt(i5);
    document.getElementById('exento').textContent=fmt(ex);
    document.getElementById('totIva').textContent=fmt(totIva);
    document.getElementById('totGen').textContent=fmt(total);

    // ✅ crédito
    if (modoPagoSel.value === 'credito') {
      const cuotas = Math.max(1, parseInt(cCuotas.value||'3',10));
      const entrega = (+cEntrega.value||0);
      const financiable = Math.max(0, total - entrega);

      const currentMonto = (+cMonto.value||0);
      if (currentMonto <= 0) {
        const calcMonto = (financiable / cuotas);
        cMonto.value = (Math.round(calcMonto*100)/100);
      }

      const montoCuota = (+cMonto.value||0);
      const totalCredito = (montoCuota * cuotas) + entrega;
      cTotal.value = (Math.round(totalCredito*100)/100);
    }
  }

  async function fetchProductById(id){
    const res=await fetch(@json($productFindUrl)+'/'+id);
    if(res.ok) return res.json();
    return null;
  }
  async function searchProducts(q){
    const res=await fetch(@json($productSearchUrl)+'?q='+encodeURIComponent(q));
    return res.ok?await res.json():[];
  }

  function wireRow(tr){
    tr.querySelectorAll('.price,.qty,.iva').forEach(inp=>inp.addEventListener('input',recalc));
    tr.querySelector('.del').addEventListener('click',()=>{tr.remove();recalc();});

    const pid=tr.querySelector('.pid');
    pid.addEventListener('blur',async()=>{ if(!pid.value) return;
      const p=await fetchProductById(pid.value.trim());
      if(p){
        tr.querySelector('.pname').value=p.name;
        tr.querySelector('.price').value=p.price_cash||0;
        recalc();
      }
    });

    const pname=tr.querySelector('.pname'), box=tr.querySelector('.suggest');
    const doSearch=debounce(async()=>{ if(pname.value.trim().length<1){ box.classList.add('hidden'); return; }
      const items=await searchProducts(pname.value.trim());
      if(!items.length){ box.classList.add('hidden'); return; }
      box.innerHTML=items.map((p,i)=>`
        <div data-i="${i}" class="pick px-3 py-2 hover:bg-gray-700 cursor-pointer">
          ID:${p.id} · ${p.name} · Gs.${Math.round(p.price_cash||0).toLocaleString('es-PY')}
        </div>`).join('');
      box.classList.remove('hidden');
      box.querySelectorAll('.pick').forEach(n=>{
        n.addEventListener('mousedown',e=>{ e.preventDefault();
          const p=items[n.dataset.i];
          pid.value=p.id;
          pname.value=p.name;
          tr.querySelector('.price').value=p.price_cash||0;
          box.classList.add('hidden');
          recalc();
        });
      });
    },300);

    pname.addEventListener('input',doSearch);
    pname.addEventListener('focus',doSearch);
    pname.addEventListener('blur',()=>setTimeout(()=>box.classList.add('hidden'),120));
  }

  function addRow(){
    const n=tpl.content.cloneNode(true);
    body.appendChild(n);
    wireRow(body.lastElementChild);
    recalc();
  }
  add.addEventListener('click',addRow);

  body.querySelectorAll('tr').forEach(wireRow);

  [modoPagoSel, cCuotas, cEntrega, cMonto].forEach(el => el?.addEventListener('input', ()=>{ showHideCredit(); recalc(); }));
  showHideCredit();
  recalc();

  document.getElementById('sale-form').addEventListener('submit',e=>{
    [...document.querySelectorAll('input[name^="items["]')].forEach(n=>n.remove());

    let idx=0;
    body.querySelectorAll('tr').forEach(tr=>{
      const id=tr.querySelector('.pid').value;
      const name=tr.querySelector('.pname').value;
      const price=tr.querySelector('.price').value;
      const qty=tr.querySelector('.qty').value;
      const iva=tr.querySelector('.iva').value;

      if(!id || (+qty)<=0) return;

      const addH=(n,v)=>{
        const h=document.createElement('input');
        h.type='hidden';
        h.name=`items[${idx}][${n}]`;
        h.value=v;
        e.target.appendChild(h);
      };

      addH('product_id',id);
      addH('product_name',name);
      addH('unit_price',price);
      addH('qty',qty);
      addH('iva_type',iva);

      idx++;
    });

    if(idx===0){
      e.preventDefault();
      alert('Agregá al menos un ítem');
      return;
    }
  });

})();
</script>
@endsection
