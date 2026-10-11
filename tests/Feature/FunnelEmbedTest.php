<?php

namespace Tests\Feature;

use App\Models\Calendar;
use App\Models\Contact;
use App\Models\Funnel;
use App\Models\Location;
use App\Models\Opportunity;
use App\Models\Pipeline;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FunnelEmbedTest extends TestCase
{
    use RefreshDatabase;

    private function makePublishedFunnel(array $attributes = []): Funnel
    {
        $location = Location::factory()->create();

        $pipeline = Pipeline::factory()->create([
            'location_id' => $location->id,
            'is_default' => true,
        ]);
        $pipeline->stages()->create(['name' => 'New Leads', 'position' => 0]);

        return Funnel::factory()->create(array_merge([
            'location_id' => $location->id,
            'slug' => 'embed-funnel',
            'headline' => 'Get 20% Off Today',
            'subheadline' => 'Limited time offer',
            'button_text' => 'Claim My Offer',
            'is_published' => true,
        ], $attributes));
    }

    public function test_embed_page_renders_the_form_for_a_published_funnel(): void
    {
        $this->makePublishedFunnel();

        $response = $this->get('/f/embed-funnel/embed');

        $response->assertOk();
        $response->assertSee('Get 20% Off Today');
        $response->assertSee('Limited time offer');
        $response->assertSee('Claim My Offer');
        // Posts to the existing submit endpoint, flagged as an embed.
        $response->assertSee('action="'.route('funnels.public.submit', 'embed-funnel').'"', false);
        $response->assertSee('name="embed" value="1"', false);
    }

    public function test_embed_page_404s_for_an_unpublished_funnel(): void
    {
        $this->makePublishedFunnel(['slug' => 'unpublished-funnel', 'is_published' => false]);

        $this->get('/f/unpublished-funnel/embed')->assertNotFound();
    }

    public function test_embed_page_404s_for_a_nonexistent_slug(): void
    {
        $this->get('/f/does-not-exist/embed')->assertNotFound();
    }

    public function test_embed_route_does_not_send_a_frame_blocking_header(): void
    {
        $this->makePublishedFunnel();

        $response = $this->get('/f/embed-funnel/embed');

        $response->assertOk();
        $this->assertNull($response->headers->get('X-Frame-Options'));
        $this->assertNull($response->headers->get('Content-Security-Policy'));
    }

    public function test_the_non_embed_public_funnel_page_still_has_frame_protection(): void
    {
        $this->makePublishedFunnel();

        $response = $this->get('/f/embed-funnel');

        $response->assertOk();
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    public function test_the_dashboard_still_has_frame_protection(): void
    {
        $location = Location::factory()->create();
        $user = User::factory()->create(['current_location_id' => $location->id]);
        $user->locations()->attach($location->id, ['role' => 'owner']);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    public function test_the_login_page_and_booking_page_still_have_frame_protection(): void
    {
        $this->makePublishedFunnel();

        $this->get('/login')->assertOk()->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $this->get('/f/embed-funnel/book')->assertOk()->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    public function test_an_embedded_submission_captures_the_lead_and_returns_to_the_embed_thank_you_state(): void
    {
        $funnel = $this->makePublishedFunnel();

        $response = $this->post('/f/embed-funnel/submit', [
            'name' => 'Jane',
            'email' => 'jane@example.com',
            'phone' => '555-0100',
            'embed' => '1',
        ]);

        $response->assertRedirect(route('funnels.public.embed', ['slug' => 'embed-funnel', 'submitted' => 1]));

        $this->assertSame(1, Contact::withoutGlobalScopes()->where('location_id', $funnel->location_id)->count());
        $this->assertSame(1, Opportunity::withoutGlobalScopes()->where('location_id', $funnel->location_id)->count());

        // The thank-you state is carried by the query string, not the
        // session, since a cross-site iframe never sends the session cookie.
        $this->flushSession();
        $this->get('/f/embed-funnel/embed?submitted=1')
            ->assertOk()
            ->assertSee('We will send you the offer code shortly.')
            ->assertDontSee('Claim My Offer');
    }

    public function test_a_non_embedded_submission_still_redirects_to_the_full_public_page(): void
    {
        $this->makePublishedFunnel();

        $this->post('/f/embed-funnel/submit', [
            'name' => 'Jane',
            'email' => 'jane@example.com',
            'phone' => '555-0100',
        ])->assertRedirect(route('funnels.public.show', 'embed-funnel'));
    }

    public function test_embed_thank_you_links_to_booking_in_a_new_tab_when_the_funnel_has_a_calendar(): void
    {
        $funnel = $this->makePublishedFunnel();
        $calendar = Calendar::factory()->create(['location_id' => $funnel->location_id]);
        $funnel->update(['calendar_id' => $calendar->id]);

        $this->get('/f/embed-funnel/embed?submitted=1')
            ->assertOk()
            ->assertSee('href="'.route('funnels.public.book', 'embed-funnel').'" target="_blank"', false);
    }

    /**
     * Laravel skips CSRF checks entirely while running unit tests, so the
     * normal post() helpers can't show whether the new 'f/* /submit'
     * exemption is scoped correctly. Turn that shortcut off and run the
     * real middleware directly.
     */
    public function test_csrf_exemption_covers_only_funnel_submissions(): void
    {
        $middleware = new class($this->app, $this->app['encrypter']) extends ValidateCsrfToken
        {
            protected function runningUnitTests()
            {
                return false;
            }
        };

        $next = fn () => new Response('ok');

        $funnelSubmit = Request::create('/f/any-slug/submit', 'POST');
        $funnelSubmit->setLaravelSession($this->app['session.store']);
        $this->assertSame('ok', $middleware->handle($funnelSubmit, $next)->getContent());

        $contactStore = Request::create('/contacts', 'POST');
        $contactStore->setLaravelSession($this->app['session.store']);
        $this->expectException(TokenMismatchException::class);
        $middleware->handle($contactStore, $next);
    }

    public function test_edit_page_shows_the_embed_snippet_with_the_funnels_slug(): void
    {
        $location = Location::factory()->create();
        $user = User::factory()->create(['current_location_id' => $location->id]);
        $user->locations()->attach($location->id, ['role' => 'owner']);

        $this->actingAs($user);
        $funnel = Funnel::factory()->create(['slug' => 'my-offer']);

        $this->get(route('funnels.edit', $funnel))
            ->assertOk()
            ->assertSee('Embed on your website')
            ->assertSee('<script src="'.asset('embed.js').'" data-slug="my-offer"></script>')
            ->assertSee('Copy');
    }
}
