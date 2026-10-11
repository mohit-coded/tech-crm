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
        // Funnel lead-form submissions are exempt too, because the form
        // can be embedded in an iframe on a client's own site (see
        // /f/{slug}/embed). The session cookie is SameSite=lax, which
        // browsers never send from a cross-site iframe, so an embedded
        // POST always arrives with no session and could never match a
        // CSRF token. Exempting it gives up nothing: the endpoint is
        // public and unauthenticated, so anyone can already submit a
        // lead directly; there's no logged-in session for a forged
        // request to ride on.
        $middleware->validateCsrfTokens(except: [
            'webhooks/twilio/sms',
            'webhooks/facebook/leads',
            'f/*/submit',
        ]);

        // Clickjacking protection. Laravel's default 'web' group does not
        // include FrameGuard, so before this every page could be framed
        // by any site. Appending it here sends X-Frame-Options: SAMEORIGIN
        // on every web.php response. The one route meant to be framed
        // cross-origin (funnels.public.embed) opts out with
        // ->withoutMiddleware(FrameGuard::class) in routes/web.php.
        $middleware->web(append: [
            \Illuminate\Http\Middleware\FrameGuard::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
