<?php

namespace App\Services\Facebook;

/**
 * The outcome of a FacebookLeadsClient::fetchLead() call. name/email/
 * phone are all nullable — a Lead Ad form isn't guaranteed to collect
 * any particular one of them, and (see FacebookLeadsClientImpl) this
 * app doesn't try to guess a value for a field the form never asked.
 */
final class FacebookLeadData
{
    public function __construct(
        public readonly string $leadgenId,
        public readonly ?string $name,
        public readonly ?string $email,
        public readonly ?string $phone,
        public readonly string $pageId,
    ) {
    }
}
