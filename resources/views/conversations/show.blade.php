<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ trim($contact->first_name.' '.$contact->last_name) }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    @if (session('status'))
                        <div class="mb-4 text-sm text-green-600 dark:text-green-400">
                            {{ session('status') }}
                        </div>
                    @endif

                    <div class="space-y-3">
                        @forelse ($messages as $message)
                            <div class="flex {{ $message->direction === 'outbound' ? 'justify-end' : 'justify-start' }}">
                                <div class="max-w-sm rounded-lg px-4 py-2 text-sm {{ $message->direction === 'outbound' ? 'bg-indigo-600 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-gray-100' }}">
                                    <p>{{ $message->body }}</p>
                                    <p class="mt-1 text-xs {{ $message->direction === 'outbound' ? 'text-indigo-100' : 'text-gray-500 dark:text-gray-400' }}">
                                        {{ $message->created_at->format('M j, g:i A') }}
                                    </p>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                {{ __('No messages yet.') }}
                            </p>
                        @endforelse
                    </div>

                    <form method="POST" action="{{ route('messages.store') }}" class="mt-6 flex items-start gap-2">
                        @csrf
                        <input type="hidden" name="contact_id" value="{{ $contact->id }}">

                        <div class="flex-1">
                            <x-text-input name="body" class="block w-full" type="text"
                                    placeholder="{{ __('Type a message...') }}" :value="old('body')" required autofocus />
                            <x-input-error :messages="$errors->get('body')" class="mt-2" />
                        </div>

                        <x-primary-button>
                            {{ __('Send') }}
                        </x-primary-button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
