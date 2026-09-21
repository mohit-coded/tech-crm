<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown from inside FunnelPublicController@confirmBooking's
 * DB::transaction() to trigger a rollback and signal "the slot was
 * taken by someone else" back out to the caller, which converts it into
 * the same user-facing redirect the earlier, softer
 * AvailabilitySlotCalculator re-check already uses.
 */
class AppointmentSlotUnavailableException extends RuntimeException
{
}
