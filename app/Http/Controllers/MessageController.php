<?php

namespace App\Http\Controllers;

use App\Jobs\SendSmsMessage;
use App\Models\Contact;
use App\Models\Message;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MessageController extends Controller
{
    /**
     * No view yet (Part C) — just the endpoint: create a queued
     * outbound Message and dispatch the job that actually sends it.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'contact_id' => ['required', 'integer'],
            'body' => ['required', 'string', 'max:1600'],
        ]);

        // Deliberately not using an 'exists' validation rule for
        // contact_id — exists/unique rules aren't Eloquent-aware and
        // would validate a cross-tenant contact id as "existing" (see
        // CLAUDE.md's exists-rule convention). Contact::findOrFail()
        // here runs through BelongsToLocation's global scope (the user
        // is authenticated, so the scope is live, unlike on a public
        // route) — a cross-tenant id simply isn't found and 404s before
        // any Message is ever created.
        $contact = Contact::findOrFail($validated['contact_id']);

        $message = Message::create([
            'location_id' => Auth::user()->current_location_id,
            'contact_id' => $contact->id,
            'direction' => 'outbound',
            'body' => $validated['body'],
            'status' => 'queued',
        ]);

        SendSmsMessage::dispatch($message);

        return redirect()->back()->with('status', __('Message queued.'));
    }
}
