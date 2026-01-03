@extends('layout.admin')

@section('content')
<div class="max-w-xl mx-auto bg-zinc-900 border border-zinc-700 rounded-xl p-6">
  <h1 class="text-xl font-bold text-white mb-2">Cambiar contraseña</h1>
  <p class="text-zinc-300 mb-4">Por seguridad, tenés que cambiar tu contraseña temporal para seguir usando el sistema.</p>

  @if ($errors->any())
    <div class="mb-4 rounded-lg border border-red-700 bg-red-900/30 text-red-100 p-3">
      {!! implode('<br>', $errors->all()) !!}
    </div>
  @endif

  <form method="POST" action="{{ route('password.force.update') }}">
    @csrf

    <label class="block text-zinc-200 mb-1">Contraseña actual</label>
    <input class="w-full mb-3 rounded-lg bg-zinc-800 border border-zinc-600 text-white p-2"
           type="password" name="current_password" required>

    <label class="block text-zinc-200 mb-1">Nueva contraseña</label>
    <input class="w-full mb-3 rounded-lg bg-zinc-800 border border-zinc-600 text-white p-2"
           type="password" name="password" required>

    <label class="block text-zinc-200 mb-1">Confirmar nueva contraseña</label>
    <input class="w-full mb-4 rounded-lg bg-zinc-800 border border-zinc-600 text-white p-2"
           type="password" name="password_confirmation" required>

    <button class="px-4 py-2 rounded-lg bg-emerald-500 hover:bg-emerald-400 text-black font-semibold">
      Guardar
    </button>
  </form>
</div>
@endsection
