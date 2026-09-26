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
        Schema::table('contacts', function (Blueprint $table) {
            // Only ever set when a lead originates from a Funnel — stays
            // null for Facebook Lead Ads leads and manually-created
            // contacts, which have no originating funnel.
            $table->foreignId('funnel_id')->nullable()->after('location_id')
                ->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('funnel_id');
        });
    }
};
