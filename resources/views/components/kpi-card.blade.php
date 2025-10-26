@props(['title' => 'KPI', 'value' => 0, 'color' => 'zinc'])

<div {{ $attributes->merge(['class' => 'kpi-card']) }}>
  <div class="rounded-xl border p-4 text-center">
    <div class="text-xs text-gray-500 mb-1">{{ $title }}</div>
    <div class="text-2xl font-bold text-{{ $color }}-500">
      {{ is_numeric($value) ? number_format($value, 0, ',', '.') : $value }}
    </div>
  </div>
</div>
