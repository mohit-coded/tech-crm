<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applied only to the Facebook Lead Ads webhook's POST route (never the
 * GET verification route — that's a different check entirely, done in
 * FacebookWebhookController@verify) — rejects with 403 before any other
 * logic runs if the X-Hub-Signature-256 header doesn't check out
 * against our app secret and the exact raw request body received.
 * Mirrors VerifyTwilioSignature's shape closely.
 */
class VerifyFacebookSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $header = (string) $request->header('X-Hub-Signature-256');

        abort_if(! str_starts_with($header, 'sha256='), 403, 'Missing Facebook signature.');

        $providedSignature = substr($header, strlen('sha256='));

        // Computed over the exact raw bytes of the request body — not the
        // parsed/re-encoded input array — since that's what Facebook
        // itself signs. $request->getContent() gives the raw body as
        // received, untouched by TrimStrings/ConvertEmptyStringsToNull
        // (those operate on parsed input, not the raw body).
        $expectedSignature = hash_hmac(
            'sha256',
            $request->getContent(),
            (string) config('services.facebook_ads.app_secret')
        );

        abort_unless(hash_equals($expectedSignature, $providedSignature), 403, 'Invalid Facebook signature.');

        return $next($request);
    }
}
