<?php

namespace App\Providers;

use App\Events\AppointmentCompleted;
use App\Events\OpportunityStageChanged;
use App\Listeners\EnrollContactsOnAppointmentCompletion;
use App\Listeners\EnrollContactsOnStageEntry;
use App\Services\Facebook\FacebookLeadsClient;
use App\Services\Facebook\FacebookLeadsClientImpl;
use App\Services\Google\GoogleBusinessProfileClient;
use App\Services\Google\GoogleBusinessProfileClientImpl;
use App\Services\OAuth\FacebookOAuthClient;
use App\Services\OAuth\FacebookOAuthClientImpl;
use App\Services\OAuth\GoogleOAuthClient;
use App\Services\OAuth\GoogleOAuthClientImpl;
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

        // Same lazy-singleton-behind-an-interface template as SmsSender
        // above — tests bind Tests\Fakes\FakeFacebookOAuthClient /
        // FakeGoogleOAuthClient in place of these before anything
        // resolves the interface, so no test can accidentally reach a
        // real endpoint.
        $this->app->singleton(FacebookOAuthClient::class, fn () => new FacebookOAuthClientImpl());
        $this->app->singleton(GoogleOAuthClient::class, fn () => new GoogleOAuthClientImpl());
        $this->app->singleton(FacebookLeadsClient::class, fn () => new FacebookLeadsClientImpl());
        $this->app->singleton(GoogleBusinessProfileClient::class, fn () => new GoogleBusinessProfileClientImpl());
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
        Event::listen(AppointmentCompleted::class, EnrollContactsOnAppointmentCompletion::class);
    }
}
