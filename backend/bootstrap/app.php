<?php

use App\Http\Middleware\LimitLeadPayloadSize;
use App\Support\CloudflareProxies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Cloudflare terminates TLS and forwards over plain HTTP. Without
        // this the app misreads two things that matter: every visitor looks
        // like the same Cloudflare address, collapsing the per-IP lead rate
        // limit into one shared bucket, and the scheme reads as http, so
        // Filament builds http:// URLs into an https:// page.
        $middleware->trustProxies(at: CloudflareProxies::all());

        $middleware->api(prepend: [
            LimitLeadPayloadSize::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
