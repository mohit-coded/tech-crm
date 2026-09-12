<?php

namespace App\Services;

/**
 * The outcome of an SmsSender::send() call. Deliberately always
 * returned rather than the failure path being a thrown exception, so
 * callers (SendSmsMessage) have one uniform way to branch on success/
 * failure — including for fakes used in tests.
 */
final class SmsSendResult
{
    public function __construct(
        public readonly bool $successful,
        public readonly ?string $sid = null,
        public readonly ?string $errorMessage = null,
    ) {
    }

    public static function success(string $sid): self
    {
        return new self(successful: true, sid: $sid);
    }

    public static function failure(string $errorMessage): self
    {
        return new self(successful: false, errorMessage: $errorMessage);
    }
}
