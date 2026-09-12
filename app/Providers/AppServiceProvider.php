<?php

namespace App\Providers;

use App\Services\SmsSender;
use App\Services\TwilioSmsSender;
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
        //
    }
}
