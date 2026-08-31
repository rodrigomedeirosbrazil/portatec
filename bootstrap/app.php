<?php

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    // Um unico ->withMiddleware(). Com mais de um bloco, tudo que registra
    // middleware em grupo (web/api/use/prepend/priority) acumula no OBJETO
    // Middleware, e cada chamada cria um objeto novo e faz setMiddlewareGroups(),
    // substituindo o anterior: so o ultimo bloco sobrevive, e em silencio.
    // Ja custou caro aqui - o middleware do Inertia ficou inativo por isso.
    // (redirectUsersTo e trustProxies gravam em estado estatico das classes de
    // middleware, entao aqueles nao eram afetados.)
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->redirectUsersTo(fn (Request $request) => auth()->user()?->hasRole('super_admin') ? '/admin' : '/app/dashboard');

        $middleware->trustProxies(
            at: [
                '172.21.0.0/16',
            ],
            headers: Request::HEADER_X_FORWARDED_FOR |
                Request::HEADER_X_FORWARDED_HOST |
                Request::HEADER_X_FORWARDED_PORT |
                Request::HEADER_X_FORWARDED_PROTO |
                Request::HEADER_X_FORWARDED_AWS_ELB
        );

        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
