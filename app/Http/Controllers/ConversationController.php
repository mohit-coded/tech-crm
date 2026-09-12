<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use Illuminate\View\View;

class ConversationController extends Controller
{
    /**
     * Contact is BelongsToLocation-scoped, so both queries below
     * (including the route-model-bound $contact on show()) are already
     * filtered to the acting user's current_location_id — no manual
     * location_id filtering needed.
     */
    public function index(): View
    {
        $contacts = Contact::whereHas('messages')
            ->with('latestMessage')
            ->withMax('messages', 'created_at')
            ->orderByDesc('messages_max_created_at')
            ->get();

        return view('conversations.index', ['contacts' => $contacts]);
    }

    public function show(Contact $contact): View
    {
        $messages = $contact->messages()->orderBy('created_at')->get();

        return view('conversations.show', ['contact' => $contact, 'messages' => $messages]);
    }
}
