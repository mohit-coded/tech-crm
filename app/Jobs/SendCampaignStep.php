<?php

namespace App\Jobs;

use App\Models\CampaignEnrollment;
use App\Models\CampaignStep;
use App\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Sends one CampaignStep to an enrollment's contact, then either queues
 * the next step (delayed by that next step's own delay_minutes) or marks
 * the enrollment completed. Runs with no authenticated user, same as
 * SendSmsMessage.
 */
class SendCampaignStep implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public CampaignEnrollment $enrollment,
        public CampaignStep $step,
    ) {
    }

    public function handle(): void
    {
        // Re-fetch fresh rather than trust $this->enrollment, which may
        // be a stale in-memory copy from before a cancellation. This is
        // what makes cancellation work without un-queuing a pending job:
        // every step job checks the live status before acting.
        $enrollment = CampaignEnrollment::find($this->enrollment->id);

        if (! $enrollment || $enrollment->status !== 'active') {
            return;
        }

        if ($this->step->channel === 'sms') {
            $message = Message::create([
                'location_id' => $enrollment->location_id,
                'contact_id' => $enrollment->contact_id,
                'direction' => 'outbound',
                'body' => $this->step->body,
                'status' => 'queued',
            ]);

            SendSmsMessage::dispatch($message);
        } else {
            // No email infra exists yet — a known gap, not a blocker for
            // finishing the SMS path.
            Log::info('Campaign step channel not implemented yet', [
                'campaign_step_id' => $this->step->id,
                'channel' => $this->step->channel,
            ]);
        }

        $enrollment->update(['current_step_id' => $this->step->id]);

        $nextStep = CampaignStep::where('campaign_id', $this->step->campaign_id)
            ->where('position', '>', $this->step->position)
            ->orderBy('position')
            ->first();

        if ($nextStep) {
            self::dispatch($enrollment, $nextStep)->delay(now()->addMinutes($nextStep->delay_minutes));
        } else {
            $enrollment->update(['status' => 'completed']);
        }
    }
}
