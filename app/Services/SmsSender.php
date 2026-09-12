<?php

namespace App\Services;

/**
 * Injectable contract for sending an SMS — deliberately not a static
 * facade, so a fake implementation can be bound in the container for
 * tests instead of ever making a real network call.
 */
interface SmsSender
{
    public function send(string $to, string $body): SmsSendResult;
}
