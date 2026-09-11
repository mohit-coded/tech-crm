<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Changing an enum's allowed values in place needs doctrine/dbal
     * (not a dependency here), so this drops and re-adds the column
     * instead — safe pre-production, since no real appointment rows
     * exist to preserve yet.
     */
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex(['location_id', 'status']);
            $table->dropColumn('status');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->enum('status', ['requested', 'booked', 'cancelled'])
                ->default('booked')->after('ends_at');
            $table->index(['location_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex(['location_id', 'status']);
            $table->dropColumn('status');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->enum('status', ['booked', 'cancelled'])
                ->default('booked')->after('ends_at');
            $table->index(['location_id', 'status']);
        });
    }
};
