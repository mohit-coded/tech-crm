<?php

namespace Tests\Fakes;

use App\Services\Google\GoogleBusinessProfileClient;
use App\Services\Google\GoogleLocationData;
use RuntimeException;
use Throwable;

/**
 * Test double for GoogleBusinessProfileClient — bound in the container
 * in place of GoogleBusinessProfileClientImpl so tests never make a
 * real Google Business Profile API call. Records every call made to it
 * so tests can assert what access token was used.
 */
class FakeGoogleBusinessProfileClient implements GoogleBusinessProfileClient
{
    /** @var list<string> */
    public array $calls = [];

    private ?GoogleLocationData $location = null;

    private bool $shouldThrow = false;

    private Throwable $exception;

    public function __construct()
    {
        $this->exception = new RuntimeException('Simulated Business Information API failure');
    }

    public function willReturn(?GoogleLocationData $location): void
    {
        $this->location = $location;
        $this->shouldThrow = false;
    }

    public function willThrow(?Throwable $exception = null): void
    {
        $this->shouldThrow = true;
        $this->exception = $exception ?? $this->exception;
    }

    public function fetchPrimaryLocation(string $accessToken): ?GoogleLocationData
    {
        $this->calls[] = $accessToken;

        if ($this->shouldThrow) {
            throw $this->exception;
        }

        return $this->location;
    }
}
