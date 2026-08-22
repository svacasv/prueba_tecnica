<?php

use App\Exceptions\ApiExceptionRenderer;
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
        // Aplica el limitador "api" (definido en AppServiceProvider) a todas las rutas de la API.
        $middleware->throttleApi();

        // Es una API sin páginas: a un invitado no se le redirige a ningún login,
        // se le responde 401. Sin esto, una petición sin cabecera Accept acababa
        // en un error 500 porque Laravel intentaba construir la ruta "login".
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Todas las respuestas de error de /api/* pasan por aquí (ver la clase).
        $exceptions->render(new ApiExceptionRenderer);
    })->create();
