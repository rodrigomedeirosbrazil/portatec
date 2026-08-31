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
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->redirectUsersTo(fn (Request $request) => auth()->user()?->hasRole('super_admin') ? '/admin' : '/app/dashboard');
        $middleware->trustProxies(at: [
            '172.21.0.0/16',
        ]);
    })
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(headers: Request::HEADER_X_FORWARDED_FOR |
            Request::HEADER_X_FORWARDED_HOST |
            Request::HEADER_X_FORWARDED_PORT |
            Request::HEADER_X_FORWARDED_PROTO |
            Request::HEADER_X_FORWARDED_AWS_ELB
        );

        // Precisa ficar NESTE bloco, o ultimo withMiddleware.
        //
        // Ha duas chamadas a withMiddleware() aqui, e elas se comportam de forma
        // diferente conforme o que se configura:
        //
        // - redirectUsersTo() e trustProxies() gravam em estado ESTATICO das classes
        //   de middleware (RedirectIfAuthenticated::redirectUsing, TrustProxies::at).
        //   Ambos os callbacks rodam, entao os dois blocos surtem efeito e as
        //   configuracoes acima estao ativas.
        //
        // - web()/use()/prepend()/priority() acumulam no OBJETO Middleware, e cada
        //   chamada a withMiddleware() cria um objeto novo e faz
        //   $kernel->setMiddlewareGroups(), substituindo o anterior em vez de somar.
        //   Só o ultimo bloco sobrevive.
        //
        // Registrar o middleware do Inertia no primeiro bloco, portanto, o descartava
        // silenciosamente: nenhuma tela recebia as props compartilhadas.
        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
