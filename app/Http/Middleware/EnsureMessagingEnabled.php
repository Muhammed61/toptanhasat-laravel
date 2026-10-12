<?php

namespace App\Http\Middleware;

use App\Models\SiteSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Admin site içi mesajlaşmayı kapattıysa mesajlaşma uç noktaları yanıt vermez (mesajlar silinmez). */
class EnsureMessagingEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! SiteSetting::messagingEnabled()) {
            return response()->json(['message' => 'Site içi mesajlaşma şu anda kapalı.'], 403);
        }

        return $next($request);
    }
}
