<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\Location;

/**
 * Resolves the small, fixed set of placeholders campaign step bodies
 * support. An unrecognized placeholder (a typo, or one that doesn't
 * exist yet) is deliberately left untouched rather than stripped —
 * silently eating unknown syntax would make a typo invisible instead of
 * showing up in the actual sent message where it's obvious and
 * debuggable.
 */
class ResolvesMessagePlaceholders
{
    public static function resolve(string $body, Contact $contact, Location $location): string
    {
        $replacements = [
            '{{review_link}}' => $location->google_review_url ?? '',
            '{{contact.first_name}}' => $contact->first_name ?? '',
            // Empty when the contact didn't come from a funnel (Facebook
            // lead, manual contact) or its funnel has no code set.
            '{{offer_code}}' => $contact->funnel?->offer_code ?? '',
        ];

        return strtr($body, $replacements);
    }
}
