<?php

namespace Tests\Fakes;

use App\Services\SmsSender;
use App\Services\SmsSendResult;

/**
 * Test double for SmsSender — bound in the container in place of
 * TwilioSmsSender so tests never make a real Twilio API call. Records
 * every call made to it so tests can assert what was sent.
 */
class FakeSmsSender implements SmsSender
{
    /** @var list<array{to: string, body: string}> */
    public array $calls = [];

    private bool $shouldSucceed;

    private string $sid;

    private string $errorMessage;

    public function __construct(
        bool $shouldSucceed = true,
        string $sid = 'SMfake0000000000000000000000000',
        string $errorMessage = 'Simulated Twilio failure'
    ) {
        $this->shouldSucceed = $shouldSucceed;
        $this->sid = $sid;
        $this->errorMessage = $errorMessage;
    }

    public function send(string $to, string $body): SmsSendResult
    {
        $this->calls[] = ['to' => $to, 'body' => $body];

        return $this->shouldSucceed
            ? SmsSendResult::success($this->sid)
            : SmsSendResult::failure($this->errorMessage);
    }
}
