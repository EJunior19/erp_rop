@extends('layout.admin')

@section('content')
<h1 class="text-2xl font-bold text-emerald-400 mb-4">
  🛒 Catálogo Ecommerce
</h1>

<p class="text-gray-400 mb-6">
  Vista ecommerce conectada directamente al ERP.
</p>

<div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-4">
  @foreach($products as $product)
    <div class="bg-gray-900 border border-gray-800 rounded-xl p-4">
      <h3 class="font-semibold text-white">{{ $product->name }}</h3>
      <p class="text-sm text-gray-400">Código: {{ $product->code }}</p>

      <div class="mt-3 text-emerald-400 font-bold">
        Gs. {{ number_format($product->price_cash, 0, ',', '.') }}
      </div>
    </div>
  @endforeach
</div>

<div class="mt-6">
  {{ $products->links() }}
</div>
@endsection
