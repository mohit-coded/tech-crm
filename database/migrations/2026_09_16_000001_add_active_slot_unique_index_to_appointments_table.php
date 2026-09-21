<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backstop for the "nothing exists yet to lock" double-booking race:
     * two concurrent first-time bookings for the same empty
     * (calendar_id, starts_at) slot can both pass an application-level
     * lockForUpdate() check, because there is nothing yet in the table
     * for either of them to lock — only a database-level constraint can
     * close that gap, by rejecting whichever INSERT loses the race.
     *
     * What's needed is a unique index on (calendar_id, starts_at)
     * scoped to non-cancelled appointments only — a cancelled
     * appointment must NOT block reusing its old slot. Neither database
     * this app runs on (MySQL and SQLite — see config/database.php;
     * tests run on SQLite, production on MySQL) supports a true
     * partial/filtered unique index (Postgres/SQL Server's
     * "UNIQUE (...) WHERE status <> 'cancelled'" syntax has no MySQL or
     * SQLite equivalent), so this emulates one the portable way both
     * actually support:
     *
     * A generated column, active_slot_marker, that evaluates to 1 for
     * any non-cancelled appointment and to NULL for a cancelled one,
     * included in a composite unique index alongside (calendar_id,
     * starts_at). Under standard SQL semantics — true on both MySQL and
     * SQLite — NULL is never considered equal to another NULL for
     * unique-index purposes, so any number of cancelled appointments
     * can freely share a (calendar_id, starts_at) pair (each one's
     * active_slot_marker is NULL, so no collision), while two
     * non-cancelled appointments for that same pair (both
     * active_slot_marker = 1) collide, and the database rejects the
     * second insert.
     *
     * Explicitly NOT used instead: a plain unique index on
     * (calendar_id, starts_at, status). That is NOT equivalent and
     * would be a bug, not a shortcut — status is part of the key there,
     * so it only stops two appointments with the EXACT SAME status
     * value from colliding. A 'requested' appointment and a
     * 'confirmed' appointment for the same slot would still be allowed
     * to coexist (wrong — the slot is still double-booked), while doing
     * nothing extra to correctly exempt cancelled rows either (a lone
     * 'cancelled' row would occupy (calendar_id, starts_at, 'cancelled')
     * without blocking anything, which happens to look right, but only
     * by accident of not being the status a real conflicting booking
     * would use).
     */
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->boolean('active_slot_marker')->nullable()->virtualAs(
                "CASE WHEN status <> 'cancelled' THEN 1 ELSE NULL END"
            )->after('completed_at');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->unique(
                ['calendar_id', 'starts_at', 'active_slot_marker'],
                'appointments_active_slot_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropUnique('appointments_active_slot_unique');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn('active_slot_marker');
        });
    }
};
