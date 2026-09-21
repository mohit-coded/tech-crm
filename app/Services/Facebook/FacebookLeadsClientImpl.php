<?php

namespace App\Services\Facebook;

use Illuminate\Support\Facades\Http;

/**
 * Real FacebookLeadsClient implementation, calling the documented
 * Facebook Graph API "Retrieving Lead Data" endpoint: GET /{leadgen-id}
 * with a Page access token, returning field_data as a list of
 * {name, values: [...]} pairs plus page_id.
 *
 * UNVERIFIED against a real Facebook Lead Ad — no developer credentials
 * exist for this project yet. Same situation, and same reasoning, as
 * FacebookOAuthClientImpl/GoogleOAuthClientImpl (Phase 9 Stage 1): this
 * proves out the flow's shape against Facebook's documented API, but
 * has not actually completed a real call. Tests never construct or
 * resolve this class — Tests\Fakes\FakeFacebookLeadsClient is bound in
 * its place for every test that touches the webhook.
 *
 * Field-name matching assumption, deliberately not over-engineered: a
 * Lead Ad form's fields are fully customizable in the Facebook Ads
 * dashboard, so there's no universal, guaranteed field name for "the
 * name field" etc. This recognizes only Facebook's own common/default
 * field names — 'full_name' or 'name' for the name, 'email' for email,
 * 'phone_number' or 'phone' for phone (case-insensitive) — and leaves
 * a value null if none of those match, rather than guessing at an
 * arbitrary or custom field key. Handling truly arbitrary per-form
 * field mapping would need its own configuration UI and is out of
 * scope for this stage.
 */
class FacebookLeadsClientImpl implements FacebookLeadsClient
{
    private const LEADGEN_URL_TEMPLATE = 'https://graph.facebook.com/v19.0/%s';

    /** @var list<string> */
    private const NAME_FIELDS = ['full_name', 'name'];

    /** @var list<string> */
    private const EMAIL_FIELDS = ['email'];

    /** @var list<string> */
    private const PHONE_FIELDS = ['phone_number', 'phone'];

    public function fetchLead(string $leadgenId, string $accessToken): FacebookLeadData
    {
        $response = Http::get(sprintf(self::LEADGEN_URL_TEMPLATE, $leadgenId), [
            'access_token' => $accessToken,
            'fields' => 'field_data,page_id',
        ])->throw()->json();

        $fieldData = $response['field_data'] ?? [];

        return new FacebookLeadData(
            leadgenId: $leadgenId,
            name: $this->extractField($fieldData, self::NAME_FIELDS),
            email: $this->extractField($fieldData, self::EMAIL_FIELDS),
            phone: $this->extractField($fieldData, self::PHONE_FIELDS),
            pageId: (string) ($response['page_id'] ?? ''),
        );
    }

    /**
     * @param  list<array{name?: string, values?: list<string>}>  $fieldData
     * @param  list<string>  $candidateNames
     */
    private function extractField(array $fieldData, array $candidateNames): ?string
    {
        foreach ($fieldData as $field) {
            if (in_array(strtolower((string) ($field['name'] ?? '')), $candidateNames, true)) {
                return $field['values'][0] ?? null;
            }
        }

        return null;
    }
}
