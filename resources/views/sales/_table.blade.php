@forelse($sales as $s)
  {{-- TODO tu row igual --}}
@empty
  <tr>
    <td colspan="9" class="px-4 py-10 text-center text-zinc-400 italic">
      🚫 No hay ventas registradas
    </td>
  </tr>
@endforelse
