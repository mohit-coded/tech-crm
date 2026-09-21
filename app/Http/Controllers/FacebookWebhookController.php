<?php

namespace App\Http\Controllers;

use App\Models\ConnectedAccount;
use App\Services\CapturesLeads;
use App\Services\Facebook\FacebookLeadsClient;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FacebookWebhookController extends Controller
{
    /**
     * The one-time webhook verification handshake Facebook performs
     * when the webhook subscription is first configured (and whenever
     * it's re-verified). Public — no auth, no signature check, since
     * there's no payload to sign yet and this is how Facebook confirms
     * it's actually reaching this app in the first place.
     */
    public function verify(Request $request): Response
    {
        // Facebook's documented query param names use literal dots
        // (hub.mode, hub.verify_token, hub.challenge), but PHP renames
        // dots (and spaces) in top-level GET/POST parameter names to
        // underscores when populating $_GET — a long-standing PHP
        // quirk, not a Laravel one — so by the time this Request
        // exists, they're already hub_mode/hub_verify_token/
        // hub_challenge. Reading the literal dotted names here would
        // silently always return null.
        $verifyToken = (string) $request->query('hub_verify_token');
        $challenge = (string) $request->query('hub_challenge');

        $isValid = hash_equals((string) config('services.facebook_ads.webhook_verify_token'), $verifyToken);

        abort_unless($isValid, 403, 'Invalid Facebook webhook verify token.');

        // Facebook expects the raw challenge string back, not JSON.
        return response($challenge, 200)->header('Content-Type', 'text/plain');
    }

    /**
     * The actual lead notification delivery — protected by
     * VerifyFacebookSignature (see routes/web.php), which has already
     * run and rejected an invalid/missing signature before this method
     * body ever executes.
     */
    public function leads(Request $request): Response
    {
        // Documented Facebook Lead Ads webhook payload shape:
        // {"object": "page", "entry": [{"id": "<page_id>", "changes":
        // [{"field": "leadgen", "value": {"leadgen_id": "...",
        // "page_id": "...", "form_id": "...", ...}}]}]}. A single
        // delivery can legitimately batch multiple entries/changes.
        foreach ((array) $request->input('entry', []) as $entry) {
            foreach ((array) ($entry['changes'] ?? []) as $change) {
                if (($change['field'] ?? null) !== 'leadgen') {
                    continue;
                }

                $value = $change['value'] ?? [];
                $leadgenId = $value['leadgen_id'] ?? null;
                $pageId = $value['page_id'] ?? null;

                if (! $leadgenId || ! $pageId) {
                    continue;
                }

                $this->processLead((string) $leadgenId, (string) $pageId);
            }
        }

        return response('', 200);
    }

    private function processLead(string $leadgenId, string $pageId): void
    {
        // Explicit filtering, not any scope — there's no authenticated
        // user on this public webhook route, same reasoning as every
        // other public route's tenant lookup in this app.
        $connectedAccount = ConnectedAccount::where('provider', 'facebook')
            ->where('external_account_id', $pageId)
            ->first();

        if (! $connectedAccount) {
            // Not every page_id Facebook might send us belongs to a
            // location using this app — same "unrecognized number"
            // reasoning as TwilioWebhookController. Not a transient
            // failure, nothing worth Facebook retrying.
            return;
        }

        $lead = app(FacebookLeadsClient::class)->fetchLead($leadgenId, $connectedAccount->access_token);

        // Shared with FunnelPublicController@store — see CapturesLeads
        // for why this was extracted rather than duplicated a second
        // time.
        app(CapturesLeads::class)->capture(
            $connectedAccount->location_id,
            $lead->name,
            $lead->email,
            $lead->phone,
            'Facebook Lead Ad'
        );
    }
}
