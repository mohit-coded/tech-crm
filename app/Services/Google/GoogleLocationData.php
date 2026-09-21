<?php

namespace App\Services\Google;

/**
 * The outcome of a GoogleBusinessProfileClient::fetchPrimaryLocation()
 * call. Both fields are plain strings — placeId can come back empty if
 * a Google Business Profile location exists but hasn't been verified/
 * matched to a Google Maps place yet, which callers must check for
 * explicitly rather than assuming a non-null GoogleLocationData always
 * means a usable placeId.
 */
final class GoogleLocationData
{
    public function __construct(
        public readonly string $placeId,
        public readonly string $locationName,
    ) {
    }
}
