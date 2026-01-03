<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ForcePasswordChange
{
  public function handle(Request $request, Closure $next)
  {
    $user = $request->user();

    if ($user && $user->must_change_password) {

      if (!$request->is('cambiar-contrasenha') && !$request->is('logout')) {

        if ($user->temp_password_expires_at && now()->greaterThan($user->temp_password_expires_at)) {
          auth()->logout();
          return redirect('/login')->withErrors([
            'email' => 'Tu contraseña temporal venció. Pedile al admin una nueva.',
          ]);
        }

        return redirect('/cambiar-contrasenha');
      }
    }

    return $next($request);
  }
}
