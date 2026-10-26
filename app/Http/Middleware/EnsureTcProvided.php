<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * İlan vermek ve mesaj yazmak için TC kimlik numarası gerekir (doğrulama gelmeden önce üye olanlar
 * Profilim'den ekler). Yöneticiler muaf. Site "code" alanına bakıp doğrulama sayfasına yönlendirir.
 */
class EnsureTcProvided
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->is_admin && ! $user->hasTc()) {
            return response()->json([
                'message' => 'Devam etmek için TC kimlik numaranızı ekleyin.',
                'code' => 'tc_required',
            ], 403);
        }

        return $next($request);
    }
}
