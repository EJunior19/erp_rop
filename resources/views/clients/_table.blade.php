<tbody class="divide-y divide-gray-800">
  @forelse($clients as $c)
    <tr class="hover:bg-gray-800/60 transition">
      <td class="px-6 py-3">{{ $c->id }}</td>
      <td class="px-6 py-3">{{ $c->code }}</td>
      <td class="px-6 py-3">
        <div class="flex flex-col">
          <span>{{ $c->name }}</span>
          @if($c->ruc)
            <span class="text-xs text-gray-400">RUC: {{ $c->ruc }}</span>
          @endif
        </div>
      </td>
      <td class="px-6 py-3">{{ $c->email }}</td>
      <td class="px-6 py-3">{{ $c->phone ?? '—' }}</td>
      <td class="px-6 py-3">
        @if($c->active)
          <span class="px-2 py-0.5 text-xs rounded bg-emerald-900 text-emerald-200 border border-emerald-700">Activo</span>
        @else
          <span class="px-2 py-0.5 text-xs rounded bg-red-900 text-red-200 border border-red-700">Inactivo</span>
        @endif
      </td>
      <td class="px-6 py-3">
        @if($c->is_test)
          <span class="px-2 py-0.5 text-xs rounded bg-yellow-900 text-yellow-200 border border-yellow-700">Prueba</span>
        @else
          <span class="px-2 py-0.5 text-xs rounded bg-blue-900 text-blue-200 border border-blue-700">Producción</span>
        @endif
      </td>
      <td class="px-6 py-3">
        <div class="flex justify-center gap-2">
          <x-action-buttons
            :show="route('clients.show',$c)"
            :edit="route('clients.edit',$c)"
            :delete="route('clients.destroy',$c)"
            :name="'el cliente '.$c->name" />
        </div>
      </td>
    </tr>
  @empty
    <tr>
      <td colspan="8" class="px-6 py-8 text-center text-gray-400">
        No se encontraron clientes con los filtros aplicados.
      </td>
    </tr>
  @endforelse
</tbody>
