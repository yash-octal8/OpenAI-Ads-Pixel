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
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->dontReport([
            \Osiset\ShopifyApp\Exceptions\MissingShopDomainException::class,
        ]);
        $exceptions->render(function (\Osiset\ShopifyApp\Exceptions\MissingShopDomainException $exception, $request) {
            return response()->view('login', [], 500);
        });
    })->create();
