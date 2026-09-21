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
        Schema::create('connected_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->enum('provider', ['facebook', 'google']);
            $table->string('external_account_id');
            $table->string('external_account_name');
            // text, not string: encrypted values (see ConnectedAccount's
            // casts) are considerably longer than the plaintext token.
            $table->text('access_token');
            $table->text('refresh_token')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('connected_at');
            $table->timestamps();

            // One connection per provider per location, not multiple —
            // reconnecting upserts the existing row (see
            // ConnectedAccountController::callback()) rather than ever
            // creating a second one.
            $table->unique(['location_id', 'provider']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('connected_accounts');
    }
};
