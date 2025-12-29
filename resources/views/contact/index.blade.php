{{-- resources/views/contact/panel.blade.php --}}
@extends('layout.admin')

@section('title','Panel de Contacto · CRM Katuete')

@push('styles')
<style>
  /* =========================================================
   *  FIX: Forzar modo oscuro SOLO en esta vista
   * ========================================================= */

  /* Scope principal */
  .contact-scope{
    background:#0b0f14;
    color:#e5e7eb;
    border-radius:16px;
    padding:16px;
  }

  /* 🔥 OVERRIDE para cualquier componente "blanco" dentro del scope */
  .contact-scope .bg-white,
  .contact-scope .bg-gray-50,
  .contact-scope .bg-gray-100,
  .contact-scope .bg-slate-50,
  .contact-scope .bg-slate-100{
    background:#0b0f14 !important;
  }

  .contact-scope .text-gray-900,
  .contact-scope .text-slate-900,
  .contact-scope .text-black{
    color:#e5e7eb !important;
  }

  .contact-scope .border-gray-200,
  .contact-scope .border-gray-300,
  .contact-scope .border-slate-200{
    border-color:#1f2937 !important;
  }

  /* Cards */
  .contact-scope .card-dark{
    background:#0f172a;
    border:1px solid rgba(16,185,129,.35);
    border-radius:14px;
  }
  .contact-scope .card-plain{
    background:#0f172a;
    border:1px solid #1f2937;
    border-radius:14px;
  }

  /* Inputs */
  .contact-scope .input-dark{
    background:#0b1220;
    border:1px solid #243043;
    color:#e5e7eb;
    border-radius:.6rem;
    outline:none;
  }
  .contact-scope .input-dark::placeholder{ color:#6b7280; }
  .contact-scope .input-dark:focus{
    border-color:#22c55e;
    box-shadow:0 0 0 3px rgba(34,197,94,.15);
  }

  /* Buttons */
  .contact-scope .btn-emerald{
    background:#059669;
    color:#fff;
  }
  .contact-scope .btn-emerald:hover{ background:#047857; }

  .contact-scope .btn-dark{
    background:#0b1220;
    border:1px solid #243043;
    color:#e5e7eb;
  }
  .contact-scope .btn-dark:hover{ background:#111b2c; }

  /* Table */
  .contact-scope .thead-dark{
    background:#0b1220;
    position:sticky;
    top:0;
    z-index:10;
    border-bottom:1px solid #1f2937;
  }
  .contact-scope .tr-hover:hover{
    background:rgba(15,23,42,.75);
  }
  .contact-scope table{
    width:100%;
  }
  .contact-scope td, .contact-scope th{
    border-color:#1f2937 !important;
  }

  /* Muted */
  .contact-scope .text-muted{ color:#94a3b8; }
  .contact-scope .border-muted{ border-color:#1f2937; }

  /* Tooltip */
  .contact-scope .tooltip{ position:relative; }
  .contact-scope .tooltip:hover::after{
    content: attr(data-tip);
    position:absolute;
    left:0;
    top:110%;
    background:#0b1220;
    border:1px solid #243043;
    color:#e5e7eb;
    padding:8px 10px;
    border-radius:10px;
    font-size:12px;
    max-width:36rem;
    white-space:pre-wrap;
    z-index:50;
    box-shadow:0 10px 25px rgba(0,0,0,.45);
  }
</style>
@endpush

@section('content')
<div class="contact-scope">

  {{-- HEADER --}}
  <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-6">
    <div>
      <h1 class="text-3xl font-bold text-emerald-400 flex items-center gap-2">
        💬 Panel de Contactos
        <span class="text-xs px-2 py-0.5 rounded bg-emerald-900/40 text-emerald-300 border border-emerald-500/30">
          Comunicaciones
        </span>
      </h1>
      <p class="text-sm text-muted mt-1">
        Envío y seguimiento de mensajes por WhatsApp, Telegram, Email y SMS.
      </p>
    </div>

    <a href="{{ route('dashboard.index') }}"
       class="px-3 py-2 rounded-lg btn-dark text-sm">
      ⬅️ Volver
    </a>
  </div>

  {{-- KPIs --}}
  <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <x-kpi-card title="Enviados hoy" :value="$kpis['sent_today']" color="emerald"/>
    <x-kpi-card title="Fallidos hoy" :value="$kpis['fails_today']" color="red"/>
    <x-kpi-card title="En cola" :value="$kpis['queued']" color="amber"/>
    <x-kpi-card title="Entregados" :value="$kpis['delivered'] ?? 0" color="emerald"/>
  </div>

  {{-- FILTROS --}}
  <form method="GET" class="grid md:grid-cols-5 gap-3 mb-6">
    <input type="text" name="q" value="{{ request('q') }}"
           placeholder="Buscar cliente / RUC"
           class="input-dark px-3 py-2">

    <select name="channel" class="input-dark px-3 py-2">
      <option value="">Todos los canales</option>
      @foreach(['telegram','whatsapp','email','sms'] as $c)
        <option value="{{ $c }}" @selected(request('channel')===$c)>{{ ucfirst($c) }}</option>
      @endforeach
    </select>

    <input type="date" name="from" value="{{ request('from') }}" class="input-dark px-3 py-2">
    <input type="date" name="to" value="{{ request('to') }}" class="input-dark px-3 py-2">

    <div class="flex gap-2">
      <button class="btn-emerald px-4 py-2 rounded-lg shadow">
        🔍 Filtrar
      </button>
      <a href="{{ route('contact.panel') }}"
         class="btn-dark px-4 py-2 rounded-lg">
        Limpiar
      </a>
    </div>
  </form>

  {{-- ENVÍO RÁPIDO --}}
  <div class="card-dark p-4 mb-6 shadow-lg">
    <h2 class="text-lg font-semibold text-emerald-300 mb-3">
      ✉️ Envío rápido de mensaje
    </h2>

    <form method="POST" action="{{ route('contact.send', $clients->first()?->id ?? 1) }}"
          onsubmit="this.action = this.action.replace(/send\/\d+/, 'send/' + document.getElementById('client_id_sel').value)">
      @csrf

      <div class="grid md:grid-cols-4 gap-3 items-end">
        <select id="client_id_sel" class="input-dark px-3 py-2">
          @foreach($clients as $c)
            <option value="{{ $c->id }}">{{ $c->code }} — {{ $c->name }}</option>
          @endforeach
        </select>

        <select name="channel" class="input-dark px-3 py-2">
          <option value="">Canal automático</option>
          <option value="telegram">Telegram</option>
          <option value="whatsapp">WhatsApp</option>
          <option value="email">Email</option>
          <option value="sms">SMS</option>
        </select>

        <input type="text" name="message" required
               placeholder="Hola 👋 tenemos novedades para vos…"
               class="input-dark md:col-span-2 px-3 py-2">
      </div>

      <div class="flex justify-end mt-3">
        <button class="btn-emerald px-4 py-2 rounded-lg shadow">
          🚀 Enviar mensaje
        </button>
      </div>
    </form>
  </div>

  {{-- TABLA --}}
  <div class="card-plain shadow-lg overflow-hidden">
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="thead-dark uppercase text-xs text-slate-300">
          <tr>
            <th class="px-4 py-3">Fecha</th>
            <th class="px-4 py-3">Cliente</th>
            <th class="px-4 py-3">Canal</th>
            <th class="px-4 py-3">Estado</th>
            <th class="px-4 py-3">Destino</th>
            <th class="px-4 py-3">Mensaje</th>
          </tr>
        </thead>

        <tbody class="divide-y border-muted">
          @forelse($logs as $l)
            <tr class="tr-hover">
              <td class="px-4 py-3 whitespace-nowrap text-muted">{{ $l->created_at?->format('Y-m-d H:i') }}</td>

              <td class="px-4 py-3">
                <a href="{{ route('clients.edit',$l->client) }}"
                   class="text-emerald-400 hover:underline">
                  {{ $l->client?->name }}
                </a>
              </td>

              <td class="px-4 py-3 capitalize text-gray-200">{{ $l->channel }}</td>

              <td class="px-4 py-3">
                <x-status-badge
                  :color="['queued'=>'amber','sent'=>'emerald','fail'=>'red'][$l->status] ?? 'zinc'"
                  :label="ucfirst($l->status)" />
              </td>

              <td class="px-4 py-3">
                @if($l->channel==='whatsapp' && data_get($l->meta,'wa_link'))
                  <a href="{{ data_get($l->meta,'wa_link') }}" target="_blank"
                     class="text-sky-400 hover:underline">
                    Abrir WhatsApp
                  </a>
                @else
                  <span class="text-muted">{{ $l->to_ref }}</span>
                @endif
              </td>

              <td class="px-4 py-3">
                <span class="tooltip block truncate max-w-[36rem]"
                      data-tip="{{ $l->message }}">
                  {{ $l->message }}
                </span>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="6" class="px-4 py-6 text-center text-muted">
                Sin registros para los filtros actuales.
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="p-4 border-t border-muted flex items-center justify-between">
      <div class="text-xs text-muted">
        Mostrando {{ $logs->firstItem() ?? 0 }} a {{ $logs->lastItem() ?? 0 }}
        de {{ $logs->total() }} registros
      </div>
      {{ $logs->withQueryString()->links() }}
    </div>
  </div>

</div>
@endsection
