{{-- resources/views/components/status-badge.blade.php --}}
@props([
  'color' => null,  // color forzado opcional
  'label' => null,  // texto del estado (puede venir en español o inglés)
  'text'  => null,  // alias por compatibilidad
])

@php
  use Illuminate\Support\Str;

  // === 1️⃣ Texto crudo y normalizado ===
  $raw = $label ?? $text ?? '';
  $raw = is_string($raw) ? $raw : '';
  $norm = Str::of($raw)->trim()->lower()->replace(' ', '_')->toString();

  // === 2️⃣ Mapear variantes ===
  $normalize = [
    'pendiente_aprobación' => 'pendiente_aprobacion',
    'pendiente_aprobacion' => 'pendiente_aprobacion',
    'pendiente'            => 'pendiente_aprobacion',
    'approved'             => 'aprobado',
    'rejected'             => 'rechazado',
    'paid'                 => 'pagado',
    'cancelled'            => 'cancelado',
    'finished'             => 'finalizado',
  ];
  $norm = $normalize[$norm] ?? $norm;

  // === 3️⃣ Mapa estado → color ===
  $stateToColor = [
    'aprobado'              => 'emerald',
    'pagado'                => 'emerald',
    'pendiente_aprobacion'  => 'amber',
    'rechazado'             => 'red',
    'vencido'               => 'red',
    'cancelado'             => 'red',
    'editable'              => 'sky',
    'finalizado'            => 'blue',
    'activo'                => 'emerald',
    'inactivo'              => 'gray',
  ];

  $autoColor = $stateToColor[$norm] ?? 'gray';
  $effColor  = $color ?: $autoColor;

  // === 4️⃣ Clases por color ===
  $palette = [
    'emerald' => 'bg-emerald-600/20 text-emerald-300 border border-emerald-600/40',
    'red'     => 'bg-red-600/20 text-red-300 border border-red-600/40',
    'amber'   => 'bg-amber-500/20 text-amber-300 border border-amber-500/40',
    'blue'    => 'bg-blue-600/20 text-blue-300 border border-blue-600/40',
    'sky'     => 'bg-sky-600/20 text-sky-300 border border-sky-600/40',
    'gray'    => 'bg-gray-600/20 text-gray-300 border border-gray-600/40',
  ];

  $style = $palette[$effColor] ?? $palette['gray'];

  // === 5️⃣ Texto mostrado (amigable) ===
  $mapToLabel = [
    'aprobado'             => 'Aprobado',
    'pendiente_aprobacion' => 'Pendiente',
    'rechazado'            => 'Rechazado',
    'editable'             => 'Editable',
    'cancelado'            => 'Cancelado',
    'finalizado'           => 'Finalizado',
    'pagado'               => 'Pagado',
    'activo'               => 'Activo',
    'inactivo'             => 'Inactivo',
  ];

  $display = $mapToLabel[$norm] ?? ucfirst($raw ?: '—');
@endphp

<span class="px-3 py-1.5 rounded-lg text-xs font-semibold {{ $style }}">
  {{ $display }}
</span>
