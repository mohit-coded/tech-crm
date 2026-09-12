<?php

namespace App\Services;

use Throwable;
use Twilio\Exceptions\TwilioException;
use Twilio\Rest\Client;

/**
 * Real SmsSender implementation, wrapping the Twilio SDK. Injected via
 * the SmsSender interface (see AppServiceProvider) rather than used as
 * a static facade, so tests can bind a fake implementation instead and
 * never make a real network call.
 */
class TwilioSmsSender implements SmsSender
{
    public function __construct(private readonly Client $client)
    {
    }

    public function send(string $to, string $body): SmsSendResult
    {
        try {
            $message = $this->client->messages->create($to, [
                'from' => config('services.twilio.phone_number'),
                'body' => $body,
            ]);

            return SmsSendResult::success($message->sid);
        } catch (TwilioException|Throwable $e) {
            // Failures are returned, not thrown — SendSmsMessage branches
            // on SmsSendResult::$successful rather than needing to catch
            // a Twilio-specific exception type itself.
            return SmsSendResult::failure($e->getMessage());
        }
    }
}
