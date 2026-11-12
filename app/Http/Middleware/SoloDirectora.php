<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guardia de la puerta: deja pasar solo a la directora.
 * Si alguien más intenta entrar, responde 403 ("prohibido").
 */
class SoloDirectora
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->esDirectora(), 403, 'Solo la directora puede hacer esto.');

        return $next($request);
    }
}
