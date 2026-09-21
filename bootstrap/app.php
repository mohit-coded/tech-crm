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
            'facebook.signature' => \App\Http\Middleware\VerifyFacebookSignature::class,
        ]);

        // Twilio's and Facebook's webhook POSTs carry no Laravel CSRF
        // token — signature verification (VerifyTwilioSignature /
        // VerifyFacebookSignature) is what authenticates these routes
        // instead. The Facebook GET verification route needs no
        // exception: CSRF is never checked on GET requests regardless.
        $middleware->validateCsrfTokens(except: [
            'webhooks/twilio/sms',
            'webhooks/facebook/leads',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
