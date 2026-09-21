<?php

namespace App\Providers;

use App\Events\OpportunityStageChanged;
use App\Listeners\EnrollContactsOnStageEntry;
use App\Services\SmsSender;
use App\Services\TwilioSmsSender;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Twilio\Rest\Client;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bound to the SmsSender interface, not resolved as a static
        // facade, so tests can swap in a fake implementation instead
        // and never make a real Twilio API call.
        $this->app->singleton(SmsSender::class, function () {
            return new TwilioSmsSender(new Client(
                config('services.twilio.sid'),
                config('services.twilio.token')
            ));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Registered explicitly rather than relying on Laravel's
        // app/Listeners auto-discovery — this app's bootstrap/app.php
        // never calls ->withEvents(), so discovery isn't active.
        Event::listen(OpportunityStageChanged::class, EnrollContactsOnStageEntry::class);
    }
}
