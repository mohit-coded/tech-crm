<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Conversations') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    @if ($contacts->isEmpty())
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ __('No conversations yet.') }}
                        </p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                <thead>
                                    <tr class="text-left text-gray-500 dark:text-gray-400">
                                        <th class="py-2 pr-4 font-medium">{{ __('Contact') }}</th>
                                        <th class="py-2 pr-4 font-medium">{{ __('Last Message') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach ($contacts as $contact)
                                        <tr>
                                            <td class="py-2 pr-4 whitespace-nowrap">
                                                <a href="{{ route('conversations.show', $contact) }}" class="underline text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-200">
                                                    {{ trim($contact->first_name.' '.$contact->last_name) }}
                                                </a>
                                            </td>
                                            <td class="py-2 pr-4 text-gray-600 dark:text-gray-400">
                                                {{ \Illuminate\Support\Str::limit($contact->latestMessage?->body ?? '', 60) }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
