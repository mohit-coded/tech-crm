<?php

namespace Tests\Unit;

use App\Models\Contact;
use App\Models\Funnel;
use App\Models\Location;
use App\Services\ResolvesMessagePlaceholders;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResolvesMessagePlaceholdersTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_review_link_and_contact_first_name(): void
    {
        $location = Location::factory()->create(['google_review_url' => 'https://g.page/r/example/review']);
        $contact = Contact::factory()->create(['location_id' => $location->id, 'first_name' => 'Jamie']);

        $resolved = ResolvesMessagePlaceholders::resolve(
            'Hi {{contact.first_name}}, please leave a review: {{review_link}}',
            $contact,
            $location
        );

        $this->assertSame(
            'Hi Jamie, please leave a review: https://g.page/r/example/review',
            $resolved
        );
    }

    public function test_an_unset_google_review_url_resolves_to_empty_string_not_the_literal_placeholder(): void
    {
        $location = Location::factory()->create(['google_review_url' => null]);
        $contact = Contact::factory()->create(['location_id' => $location->id, 'first_name' => 'Jamie']);

        $resolved = ResolvesMessagePlaceholders::resolve('Review us: {{review_link}}', $contact, $location);

        $this->assertSame('Review us: ', $resolved);
    }

    public function test_resolves_offer_code_from_the_contacts_originating_funnel(): void
    {
        $location = Location::factory()->create();
        $funnel = Funnel::factory()->create(['location_id' => $location->id, 'offer_code' => 'SPRING25']);
        $contact = Contact::factory()->create(['location_id' => $location->id, 'funnel_id' => $funnel->id]);

        $resolved = ResolvesMessagePlaceholders::resolve('Your code: {{offer_code}}', $contact, $location);

        $this->assertSame('Your code: SPRING25', $resolved);
    }

    public function test_offer_code_resolves_to_empty_string_when_the_funnel_has_no_code(): void
    {
        $location = Location::factory()->create();
        $funnel = Funnel::factory()->create(['location_id' => $location->id, 'offer_code' => null]);
        $contact = Contact::factory()->create(['location_id' => $location->id, 'funnel_id' => $funnel->id]);

        $resolved = ResolvesMessagePlaceholders::resolve('Your code: {{offer_code}}', $contact, $location);

        $this->assertSame('Your code: ', $resolved);
    }

    public function test_offer_code_resolves_to_empty_string_when_the_contact_has_no_funnel(): void
    {
        $location = Location::factory()->create();
        $contact = Contact::factory()->create(['location_id' => $location->id, 'funnel_id' => null]);

        $resolved = ResolvesMessagePlaceholders::resolve('Your code: {{offer_code}}', $contact, $location);

        $this->assertSame('Your code: ', $resolved);
    }

    public function test_an_unrecognized_placeholder_is_left_untouched(): void
    {
        $location = Location::factory()->create(['google_review_url' => 'https://example.com/review']);
        $contact = Contact::factory()->create(['location_id' => $location->id, 'first_name' => 'Jamie']);

        $resolved = ResolvesMessagePlaceholders::resolve(
            'Hi {{contact.first_name}}, {{something_else}} stays as-is.',
            $contact,
            $location
        );

        $this->assertSame('Hi Jamie, {{something_else}} stays as-is.', $resolved);
    }
}
