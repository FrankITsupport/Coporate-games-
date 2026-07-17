<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // This app has no /api/* prefix — the player/prediction answer-submission endpoints
        // (/play/*/answer, /predict/*/answer) are plain web routes consumed by fetch() from
        // Vue, not a separate API namespace. Restricting this to api/* alone meant every
        // exception on those routes (e.g. a validation failure) rendered as an HTML redirect
        // instead of JSON, breaking the frontend's `await res.json()` — found by submitting an
        // empty open-text answer against a real running server and getting a redirect instead
        // of a 422. Falling back to Laravel's normal expectsJson() check fixes it for those
        // routes without weakening anything: it only adds cases, never removes the api/* one.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
