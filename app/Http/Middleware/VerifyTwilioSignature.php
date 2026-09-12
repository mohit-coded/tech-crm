<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Twilio\Security\RequestValidator;

/**
 * Applied only to the Twilio SMS webhook route — rejects with 403
 * before any other logic runs if the X-Twilio-Signature header doesn't
 * check out against our auth token, the request URL, and the POST
 * params actually received.
 */
class VerifyTwilioSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $signature = $request->header('X-Twilio-Signature');

        abort_if(! $signature, 403, 'Missing Twilio signature.');

        $validator = new RequestValidator((string) config('services.twilio.token'));

        // request()->fullUrl() is the URL Laravel sees, which is only
        // the exact public URL Twilio POSTed to for a direct request.
        // Behind a reverse proxy or tunnel (ngrok, a load balancer, a
        // CDN) the scheme/host Laravel sees can differ from what's
        // publicly reachable, which would make a genuinely valid
        // signature fail this check — if that happens once this goes
        // behind ngrok or real hosting, look at Laravel's trusted-proxy
        // config (the TrustProxies middleware / X-Forwarded-* headers)
        // before assuming the signature itself is wrong. Assuming
        // direct requests for now.
        $isValid = $validator->validate($signature, $request->fullUrl(), $request->post());

        abort_unless($isValid, 403, 'Invalid Twilio signature.');

        return $next($request);
    }
}
