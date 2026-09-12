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
        $middleware->alias([
            'twilio.signature' => \App\Http\Middleware\VerifyTwilioSignature::class,
        ]);

        // Twilio's webhook POST carries no Laravel CSRF token — signature
        // verification (VerifyTwilioSignature) is what authenticates this
        // route instead.
        $middleware->validateCsrfTokens(except: [
            'webhooks/twilio/sms',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
