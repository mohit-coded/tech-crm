<?php

namespace App\Jobs;

use App\Models\Message;
use App\Services\SmsSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends a Message already created with status 'queued'. Runs with no
 * authenticated user (queued jobs never have one), so location_id must
 * already be set on $message before this job ever runs — nothing here
 * can rely on Auth::user() or BelongsToLocation's creating() auto-fill.
 */
class SendSmsMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Message $message)
    {
    }

    public function handle(SmsSender $sender): void
    {
        try {
            $result = $sender->send($this->message->contact->phone, $this->message->body);
        } catch (Throwable $e) {
            // Defensive: SmsSender implementations are expected to catch
            // their own failures and return a failed SmsSendResult (see
            // TwilioSmsSender), but if something unexpected still throws,
            // it must be logged here, not left to bubble up and fail the
            // job noisily.
            $this->logFailure($e->getMessage());

            return;
        }

        if ($result->successful) {
            $this->message->update([
                'status' => 'sent',
                'twilio_sid' => $result->sid,
            ]);

            return;
        }

        $this->logFailure($result->errorMessage);
    }

    private function logFailure(?string $errorMessage): void
    {
        Log::error('SMS send failed', [
            'message_id' => $this->message->id,
            'error' => $errorMessage,
        ]);

        $this->message->update(['status' => 'failed']);
    }
}
