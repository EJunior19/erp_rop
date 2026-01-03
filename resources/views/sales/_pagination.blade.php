<div class="flex justify-between items-center">
  <div class="text-xs text-zinc-400">
    Mostrando {{ $sales->firstItem() ?? 0 }}
    a {{ $sales->lastItem() ?? 0 }}
    de {{ $sales->total() }}
  </div>

  {{ $sales->withQueryString()->links() }}
</div>
