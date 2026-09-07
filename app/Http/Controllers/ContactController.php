<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContactController extends Controller
{
    /**
     * Contact is BelongsToLocation-scoped, so every query below (including
     * route-model-bound $contact params) is already filtered to the acting
     * user's current_location_id — no manual location_id filtering needed.
     */
    public function index(): View
    {
        $contacts = Contact::orderBy('first_name')->orderBy('last_name')->paginate(15);

        return view('contacts.index', ['contacts' => $contacts]);
    }

    public function create(): View
    {
        return view('contacts.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        // location_id is deliberately omitted here: BelongsToLocation's
        // creating() hook auto-fills it from Auth::user()->current_location_id,
        // which is reliable at this point because the user is authenticated
        // (unlike registration, where Auth::login() hasn't run yet).
        Contact::create($validated);

        return redirect()->route('contacts.index')->with('status', __('Contact added.'));
    }

    public function edit(Contact $contact): View
    {
        return view('contacts.edit', ['contact' => $contact]);
    }

    public function update(Request $request, Contact $contact): RedirectResponse
    {
        $contact->update($this->validated($request));

        return redirect()->route('contacts.index')->with('status', __('Contact updated.'));
    }

    public function destroy(Contact $contact): RedirectResponse
    {
        $contact->delete();

        return redirect()->route('contacts.index')->with('status', __('Contact deleted.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
        ]);
    }
}
