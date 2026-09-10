<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->foreignId('calendar_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            // Always stored in UTC — see App\Services\AvailabilitySlotCalculator,
            // which converts these into the calendar's own timezone for
            // availability comparisons.
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->enum('status', ['booked', 'cancelled'])->default('booked');
            $table->timestamps();

            $table->index(['calendar_id', 'starts_at']);
            $table->index(['location_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
