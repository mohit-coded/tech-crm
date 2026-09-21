<?php

namespace Tests\Feature;

use App\Jobs\SendCampaignStep;
use App\Jobs\SendSmsMessage;
use App\Models\Campaign;
use App\Models\CampaignEnrollment;
use App\Models\CampaignStep;
use App\Models\Contact;
use App\Models\Location;
use App\Models\Message;
use App\Services\EnrollsContacts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Phase 6 Stage 2: the execution engine that walks an already-created
 * CampaignEnrollment through its steps. Nothing here tests
 * trigger/auto-enrollment (Stage 3) — enroll() is called directly.
 */
class CampaignExecutionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Location, 1: Campaign, 2: Contact}
     */
    private function makeCampaignWithSteps(array $stepDefinitions): array
    {
        $location = Location::factory()->create();
        $campaign = Campaign::factory()->create(['location_id' => $location->id]);
        $contact = Contact::factory()->create(['location_id' => $location->id]);

        foreach ($stepDefinitions as $i => $definition) {
            CampaignStep::factory()->create(array_merge([
                'campaign_id' => $campaign->id,
                'position' => $i,
            ], $definition));
        }

        return [$location, $campaign, $contact];
    }

    public function test_enrolling_dispatches_the_first_steps_job_with_its_delay(): void
    {
        Queue::fake();
        $this->freezeTime();

        [, $campaign, $contact] = $this->makeCampaignWithSteps([
            ['channel' => 'sms', 'body' => 'Step one', 'delay_minutes' => 15],
            ['channel' => 'sms', 'body' => 'Step two', 'delay_minutes' => 60],
        ]);

        $enrollment = (new EnrollsContacts())->enroll($contact, $campaign);

        $this->assertSame('active', $enrollment->status);
        $this->assertSame($campaign->id, $enrollment->campaign_id);
        $this->assertSame($contact->id, $enrollment->contact_id);

        $firstStep = CampaignStep::where('campaign_id', $campaign->id)->where('position', 0)->sole();

        Queue::assertPushed(SendCampaignStep::class, function (SendCampaignStep $job) use ($enrollment, $firstStep) {
            return $job->enrollment->is($enrollment)
                && $job->step->is($firstStep)
                && $job->delay?->equalTo(now()->addMinutes(15));
        });
    }

    public function test_enroll_sets_location_id_explicitly_without_an_authenticated_user(): void
    {
        Queue::fake();

        // No actingAs() anywhere in this test — proves enroll() doesn't
        // rely on BelongsToLocation's auto-fill, which has nothing to
        // key off with no authenticated user.
        $location = Location::factory()->create();
        $campaign = Campaign::factory()->create(['location_id' => $location->id]);
        $contact = Contact::factory()->create(['location_id' => $location->id]);

        $enrollment = (new EnrollsContacts())->enroll($contact, $campaign);

        $this->assertSame($location->id, $enrollment->location_id);
    }

    public function test_running_a_steps_job_creates_a_message_and_dispatches_send_sms_message(): void
    {
        Queue::fake();

        [$location, $campaign, $contact] = $this->makeCampaignWithSteps([
            ['channel' => 'sms', 'body' => 'Thanks for booking!', 'delay_minutes' => 0],
        ]);

        $enrollment = CampaignEnrollment::create([
            'location_id' => $location->id,
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
            'status' => 'active',
        ]);

        $step = CampaignStep::where('campaign_id', $campaign->id)->sole();

        (new SendCampaignStep($enrollment, $step))->handle();

        $message = Message::withoutGlobalScopes()->sole();
        $this->assertSame($location->id, $message->location_id);
        $this->assertSame($contact->id, $message->contact_id);
        $this->assertSame('outbound', $message->direction);
        $this->assertSame('queued', $message->status);
        $this->assertSame('Thanks for booking!', $message->body);

        Queue::assertPushed(SendSmsMessage::class, function (SendSmsMessage $job) use ($message) {
            return $job->message->is($message);
        });
    }

    public function test_running_a_steps_job_dispatches_the_next_steps_job_with_its_own_delay(): void
    {
        Queue::fake();
        $this->freezeTime();

        [$location, $campaign, $contact] = $this->makeCampaignWithSteps([
            ['channel' => 'sms', 'body' => 'Step one', 'delay_minutes' => 0],
            ['channel' => 'sms', 'body' => 'Step two', 'delay_minutes' => 45],
        ]);

        $enrollment = CampaignEnrollment::create([
            'location_id' => $location->id,
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
            'status' => 'active',
        ]);

        $firstStep = CampaignStep::where('campaign_id', $campaign->id)->where('position', 0)->sole();
        $secondStep = CampaignStep::where('campaign_id', $campaign->id)->where('position', 1)->sole();

        (new SendCampaignStep($enrollment, $firstStep))->handle();

        $enrollment->refresh();
        $this->assertSame($firstStep->id, $enrollment->current_step_id);
        $this->assertSame('active', $enrollment->status);

        Queue::assertPushed(SendCampaignStep::class, function (SendCampaignStep $job) use ($enrollment, $secondStep) {
            return $job->enrollment->is($enrollment)
                && $job->step->is($secondStep)
                && $job->delay?->equalTo(now()->addMinutes(45));
        });
    }

    public function test_running_the_last_steps_job_marks_the_enrollment_completed_and_dispatches_nothing_further(): void
    {
        Queue::fake();

        [$location, $campaign, $contact] = $this->makeCampaignWithSteps([
            ['channel' => 'sms', 'body' => 'Only step', 'delay_minutes' => 0],
        ]);

        $enrollment = CampaignEnrollment::create([
            'location_id' => $location->id,
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
            'status' => 'active',
        ]);

        $step = CampaignStep::where('campaign_id', $campaign->id)->sole();

        (new SendCampaignStep($enrollment, $step))->handle();

        $enrollment->refresh();
        $this->assertSame('completed', $enrollment->status);
        $this->assertSame($step->id, $enrollment->current_step_id);

        Queue::assertNotPushed(SendCampaignStep::class);
    }

    public function test_running_a_step_job_on_a_cancelled_enrollment_sends_nothing_and_dispatches_nothing_further(): void
    {
        Queue::fake();

        [$location, $campaign, $contact] = $this->makeCampaignWithSteps([
            ['channel' => 'sms', 'body' => 'Step one', 'delay_minutes' => 0],
            ['channel' => 'sms', 'body' => 'Step two', 'delay_minutes' => 30],
        ]);

        $enrollment = CampaignEnrollment::create([
            'location_id' => $location->id,
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
            'status' => 'active',
        ]);

        $firstStep = CampaignStep::where('campaign_id', $campaign->id)->where('position', 0)->sole();
        $secondStep = CampaignStep::where('campaign_id', $campaign->id)->where('position', 1)->sole();

        // The job object below is constructed with the enrollment as it
        // was BEFORE cancellation — handle() must not trust that stale
        // in-memory copy and must re-fetch the live status from the DB.
        $staleJob = new SendCampaignStep($enrollment, $secondStep);

        $enrollment->update(['status' => 'cancelled']);

        $staleJob->handle();

        $this->assertSame(0, Message::withoutGlobalScopes()->count());
        Queue::assertNotPushed(SendSmsMessage::class);
        Queue::assertNotPushed(SendCampaignStep::class);

        $enrollment->refresh();
        $this->assertSame('cancelled', $enrollment->status);
        $this->assertNull($enrollment->current_step_id);

        // Sanity check the harness itself, not just the cancelled path:
        // running the FIRST step on the same now-cancelled enrollment is
        // also a no-op.
        (new SendCampaignStep($enrollment, $firstStep))->handle();
        $this->assertSame(0, Message::withoutGlobalScopes()->count());
    }

    public function test_cancel_marks_the_enrollment_cancelled(): void
    {
        [$location, $campaign, $contact] = $this->makeCampaignWithSteps([
            ['channel' => 'sms', 'body' => 'Step one', 'delay_minutes' => 0],
        ]);

        $enrollment = CampaignEnrollment::create([
            'location_id' => $location->id,
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
            'status' => 'active',
        ]);

        (new EnrollsContacts())->cancel($enrollment);

        $enrollment->refresh();
        $this->assertSame('cancelled', $enrollment->status);
    }
}
