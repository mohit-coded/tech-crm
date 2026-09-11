<?php

namespace Tests\Feature;

use App\Models\Calendar;
use App\Models\Contact;
use App\Models\Funnel;
use App\Models\Location;
use App\Models\Opportunity;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FunnelPublicControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Location, 1: Pipeline, 2: PipelineStage}
     */
    private function makeLocationWithPipeline(): array
    {
        $location = Location::factory()->create();

        $pipeline = Pipeline::factory()->create([
            'location_id' => $location->id,
            'is_default' => true,
        ]);

        $stage = $pipeline->stages()->create(['name' => 'New Leads', 'position' => 0]);

        return [$location, $pipeline, $stage];
    }

    public function test_public_page_renders_for_a_published_funnel(): void
    {
        [$location] = $this->makeLocationWithPipeline();

        $funnel = Funnel::factory()->create([
            'location_id' => $location->id,
            'slug' => 'published-funnel',
            'headline' => 'Book Your Free Consult',
            'subheadline' => 'Spots are limited',
            'button_text' => 'Book Now',
            'is_published' => true,
        ]);

        $response = $this->get('/f/published-funnel');

        $response->assertOk();
        $response->assertSee('Book Your Free Consult');
        $response->assertSee('Spots are limited');
        $response->assertSee('Book Now');
    }

    public function test_public_page_404s_for_an_unpublished_funnel(): void
    {
        [$location] = $this->makeLocationWithPipeline();

        Funnel::factory()->create([
            'location_id' => $location->id,
            'slug' => 'unpublished-funnel',
            'is_published' => false,
        ]);

        $response = $this->get('/f/unpublished-funnel');

        $response->assertNotFound();
    }

    public function test_public_page_404s_for_a_nonexistent_slug(): void
    {
        $response = $this->get('/f/does-not-exist');

        $response->assertNotFound();
    }

    public function test_submitting_the_form_creates_exactly_one_contact_and_opportunity_scoped_to_the_funnels_location(): void
    {
        [$location, $pipeline, $stage] = $this->makeLocationWithPipeline();

        $funnel = Funnel::factory()->create([
            'location_id' => $location->id,
            'name' => 'Spring Promo',
            'slug' => 'spring-promo',
            'is_published' => true,
        ]);

        $response = $this->post('/f/spring-promo/submit', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '555-1234',
        ]);

        $response->assertRedirect(route('funnels.public.show', 'spring-promo'));

        $contact = Contact::withoutGlobalScopes()->sole();
        $this->assertSame($location->id, $contact->location_id);
        $this->assertSame('Jane Doe', $contact->first_name);
        $this->assertSame('jane@example.com', $contact->email);
        $this->assertSame('555-1234', $contact->phone);

        $opportunity = Opportunity::withoutGlobalScopes()->sole();
        $this->assertSame($location->id, $opportunity->location_id);
        $this->assertSame($contact->id, $opportunity->contact_id);
        $this->assertSame($pipeline->id, $opportunity->pipeline_id);
        $this->assertSame($stage->id, $opportunity->pipeline_stage_id);
        $this->assertSame('open', $opportunity->status);
        $this->assertSame('Spring Promo', $opportunity->source);
    }

    public function test_submitting_funnel_a_never_creates_data_in_funnel_bs_location(): void
    {
        [$locationA] = $this->makeLocationWithPipeline();
        [$locationB] = $this->makeLocationWithPipeline();

        Funnel::factory()->create([
            'location_id' => $locationA->id,
            'slug' => 'funnel-a',
            'is_published' => true,
        ]);
        Funnel::factory()->create([
            'location_id' => $locationB->id,
            'slug' => 'funnel-b',
            'is_published' => true,
        ]);

        $response = $this->post('/f/funnel-a/submit', [
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'phone' => '555-0001',
        ]);

        $response->assertRedirect(route('funnels.public.show', 'funnel-a'));

        $this->assertSame(0, Contact::withoutGlobalScopes()->where('location_id', $locationB->id)->count());
        $this->assertSame(0, Opportunity::withoutGlobalScopes()->where('location_id', $locationB->id)->count());

        $this->assertSame(1, Contact::withoutGlobalScopes()->where('location_id', $locationA->id)->count());
        $this->assertSame(1, Opportunity::withoutGlobalScopes()->where('location_id', $locationA->id)->count());
    }

    public function test_thank_you_page_shows_a_booking_link_when_the_funnel_has_a_calendar(): void
    {
        [$location] = $this->makeLocationWithPipeline();

        $calendar = Calendar::factory()->create(['location_id' => $location->id]);

        Funnel::factory()->create([
            'location_id' => $location->id,
            'calendar_id' => $calendar->id,
            'slug' => 'with-calendar',
            'is_published' => true,
        ]);

        $this->post('/f/with-calendar/submit', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '555-1234',
        ]);

        $response = $this->get('/f/with-calendar');

        $response->assertOk();
        $response->assertSee('Thank you!');
        $response->assertSee('Book Your Appointment');
    }

    public function test_thank_you_page_does_not_show_a_booking_link_when_the_funnel_has_no_calendar(): void
    {
        [$location] = $this->makeLocationWithPipeline();

        Funnel::factory()->create([
            'location_id' => $location->id,
            'calendar_id' => null,
            'slug' => 'no-calendar',
            'is_published' => true,
        ]);

        $this->post('/f/no-calendar/submit', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '555-1234',
        ]);

        $response = $this->get('/f/no-calendar');

        $response->assertOk();
        $response->assertSee('Thank you!');
        $response->assertDontSee('Book Your Appointment');
    }
}
