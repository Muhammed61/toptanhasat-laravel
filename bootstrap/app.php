<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Vue SPA, Sanctum ile çerez (session) tabanlı oturum kullanır
        $middleware->statefulApi();

        // Yayında HTTPS, Cloudflare/nginx'te sonlanır ve istek Laravel'e http olarak gelir.
        // Vekil sunucuların X-Forwarded-* başlıklarına güvenilir ki üretilen adresler
        // (örn. Livewire'ın /livewire/update'i) https olsun — yoksa "Mixed Content" hatası olur.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
