<?php

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\ConnectedAccountController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FacebookWebhookController;
use App\Http\Controllers\FunnelController;
use App\Http\Controllers\FunnelPublicController;
use App\Http\Controllers\LocationSettingsController;
use App\Http\Controllers\LocationSwitchController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\OpportunityBoardController;
use App\Http\Controllers\OpportunityStageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TwilioWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::post('/locations/switch/{location}', [LocationSwitchController::class, 'switch'])
        ->name('locations.switch');

    // One settings page per location, not a list — no {location} route
    // parameter, see LocationSettingsController for why that keeps this
    // tenant-safe by construction.
    Route::get('/settings', [LocationSettingsController::class, 'edit'])->name('settings.location.edit');
    Route::put('/settings', [LocationSettingsController::class, 'update'])->name('settings.location.update');

    // connect()/callback() stay inside 'auth' rather than being public
    // like the Twilio webhook: this is a browser-driven redirect flow,
    // not a server-to-server one. The same browser session that starts
    // it at connect() is the one the provider redirects back to
    // callback() — an external redirect in between doesn't clear our
    // session cookie, since cookies are scoped to our own domain, not
    // the referring one — so Auth::user() is still available exactly
    // when callback() needs it to know which location to attach the
    // connection to (unlike the Twilio webhook, nothing else tells us
    // that here). If the session genuinely did expire mid-flow, 'auth'
    // correctly bounces to login rather than the controller having to
    // guess a location — the right behavior either way. The 'state'
    // param (see ConnectedAccountController::connect()) is a separate
    // concern from this — it guards against a forged/replayed callback,
    // not against the lack of a session.
    Route::get('/connected-accounts/{provider}/connect', [ConnectedAccountController::class, 'connect'])
        ->whereIn('provider', ['facebook', 'google'])
        ->name('connected-accounts.connect');
    Route::get('/connected-accounts/{provider}/callback', [ConnectedAccountController::class, 'callback'])
        ->whereIn('provider', ['facebook', 'google'])
        ->name('connected-accounts.callback');
    Route::delete('/connected-accounts/{connectedAccount}', [ConnectedAccountController::class, 'disconnect'])
        ->name('connected-accounts.disconnect');

    Route::resource('contacts', ContactController::class)->except('show');

    Route::resource('funnels', FunnelController::class)->except('show');

    Route::resource('calendars', CalendarController::class)->except('show');

    Route::resource('campaigns', CampaignController::class)->except('show');

    Route::get('/appointments', [AppointmentController::class, 'index'])->name('appointments.index');
    Route::post('/appointments/{appointment}/confirm', [AppointmentController::class, 'confirm'])->name('appointments.confirm');
    Route::post('/appointments/{appointment}/cancel', [AppointmentController::class, 'cancel'])->name('appointments.cancel');
    Route::post('/appointments/{appointment}/complete', [AppointmentController::class, 'complete'])->name('appointments.complete');

    Route::post('/messages', [MessageController::class, 'store'])->name('messages.store');

    Route::get('/conversations', [ConversationController::class, 'index'])->name('conversations.index');
    Route::get('/conversations/{contact}', [ConversationController::class, 'show'])->name('conversations.show');

    Route::get('/opportunities', [OpportunityBoardController::class, 'index'])
        ->name('opportunities.index');

    // Same-origin fetch() from board.blade.php, session-authenticated —
    // belongs here, not in api.php (see CLAUDE.md routing convention).
    Route::patch('/api/opportunities/{opportunity}/stage', [OpportunityStageController::class, 'update']);
});

// Public funnel pages — anonymous visitors, deliberately outside the
// 'auth' group. FunnelPublicController resolves tenancy explicitly via
// the funnel's location_id rather than relying on BelongsToLocation's
// scope, which is inert with no authenticated user.
Route::get('/f/{slug}', [FunnelPublicController::class, 'show'])->name('funnels.public.show');
Route::post('/f/{slug}/submit', [FunnelPublicController::class, 'store'])->name('funnels.public.submit');
Route::get('/f/{slug}/book', [FunnelPublicController::class, 'book'])->name('funnels.public.book');
Route::post('/f/{slug}/book/confirm', [FunnelPublicController::class, 'confirmBooking'])->name('funnels.public.book.confirm');
Route::get('/f/{slug}/book/confirmed', [FunnelPublicController::class, 'bookingConfirmed'])->name('funnels.public.book.confirmed');

// Twilio SMS webhook — public (not in the 'auth' group), but protected
// by signature verification instead (see VerifyTwilioSignature), which
// runs before any other logic and rejects with 403 on a bad/missing
// signature. Also excluded from CSRF verification in bootstrap/app.php,
// since Twilio's POST carries no Laravel CSRF token.
Route::post('/webhooks/twilio/sms', [TwilioWebhookController::class, 'sms'])
    ->middleware('twilio.signature')
    ->name('webhooks.twilio.sms');

// Facebook Lead Ads webhook — public, split across two routes on the
// same path because it's two entirely different checks: the GET
// verification handshake (a one-time/occasional setup step, no
// signature possible since there's no payload yet — see
// FacebookWebhookController@verify) and the POST delivery of actual
// lead notifications, which IS signature-protected (facebook.signature,
// see VerifyFacebookSignature) and excluded from CSRF verification in
// bootstrap/app.php, same reasoning as the Twilio webhook.
Route::get('/webhooks/facebook/leads', [FacebookWebhookController::class, 'verify'])
    ->name('webhooks.facebook.leads.verify');
Route::post('/webhooks/facebook/leads', [FacebookWebhookController::class, 'leads'])
    ->middleware('facebook.signature')
    ->name('webhooks.facebook.leads');

require __DIR__.'/auth.php';
