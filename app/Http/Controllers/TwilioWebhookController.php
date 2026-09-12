<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Location;
use App\Models\Message;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TwilioWebhookController extends Controller
{
    public function sms(Request $request): Response
    {
        $to = (string) $request->input('To');

        // Matching by the 'To' number against locations.twilio_phone_number
        // is inherently cross-tenant — there's no location_id to scope this
        // by yet, we're discovering it. That's safe specifically because
        // VerifyTwilioSignature has already run and proven this request
        // came from OUR Twilio account about a message sent to one of OUR
        // verified numbers — we're trusting Twilio's signed assertion of
        // which of our own numbers received it, not an arbitrary claim
        // from an unauthenticated client the way a public form submission
        // would be.
        $location = Location::where('twilio_phone_number', $to)->first();

        if (! $location) {
            // Not a transient failure — retrying won't make an unrecognized
            // number recognized. Respond 200 so Twilio doesn't retry.
            return $this->emptyTwiml();
        }

        $from = (string) $request->input('From');

        // Scoped explicitly by location_id, same discipline as Funnels'
        // public routes — BelongsToLocation's scope is inert here (no
        // authenticated user), so this filter is what actually keeps the
        // lookup tenant-safe.
        $contact = Contact::where('location_id', $location->id)
            ->where('phone', $from)
            ->first();

        if (! $contact) {
            $contact = Contact::create([
                'location_id' => $location->id,
                'first_name' => $from,
                'phone' => $from,
            ]);
        }

        Message::create([
            'location_id' => $location->id,
            'contact_id' => $contact->id,
            'direction' => 'inbound',
            'body' => (string) $request->input('Body'),
            'twilio_sid' => $request->input('MessageSid'),
            'status' => 'received',
        ]);

        return $this->emptyTwiml();
    }

    /**
     * A minimal valid empty TwiML response — tells Twilio the webhook
     * succeeded and there's no reply to send.
     */
    private function emptyTwiml(): Response
    {
        return response(
            '<?xml version="1.0" encoding="UTF-8"?><Response></Response>',
            200,
            ['Content-Type' => 'text/xml']
        );
    }
}
