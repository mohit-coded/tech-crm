<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Settings') }}
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

                    <form method="POST" action="{{ route('settings.location.update') }}">
                        @csrf
                        @method('PUT')

                        <div>
                            <x-input-label for="google_review_url" :value="__('Google Review Link')" />
                            <x-text-input id="google_review_url" class="block mt-1 w-full" type="url" name="google_review_url"
                                    placeholder="https://g.page/r/…/review"
                                    :value="old('google_review_url', $location->google_review_url ?? '')" />
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                {{ __('Used as the') }} <code>@{{review_link}}</code> {{ __('placeholder in campaign message bodies.') }}
                            </p>
                            <x-input-error :messages="$errors->get('google_review_url')" class="mt-2" />
                        </div>

                        <div class="flex items-center justify-end mt-6">
                            <x-primary-button>
                                {{ __('Save') }}
                            </x-primary-button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mt-6">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h3 class="text-lg font-medium mb-4">{{ __('Connected Accounts') }}</h3>

                    <div class="space-y-4">
                        @foreach (['facebook' => __('Facebook'), 'google' => __('Google')] as $provider => $label)
                            <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-700 pb-4 last:border-0 last:pb-0">
                                <div>
                                    <p class="font-medium">{{ $label }}</p>
                                    @if ($connectedAccounts->has($provider))
                                        <p class="text-sm text-gray-500 dark:text-gray-400">
                                            {{ __('Connected as :name', ['name' => $connectedAccounts[$provider]->external_account_name]) }}
                                        </p>
                                    @else
                                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Not connected') }}</p>
                                    @endif
                                </div>

                                @if ($connectedAccounts->has($provider))
                                    <form method="POST" action="{{ route('connected-accounts.disconnect', $connectedAccounts[$provider]) }}"
                                            onsubmit="return confirm('{{ __('Disconnect this account?') }}');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-sm underline text-red-600 dark:text-red-400 hover:text-red-900 dark:hover:text-red-200">
                                            {{ __('Disconnect') }}
                                        </button>
                                    </form>
                                @else
                                    <a href="{{ route('connected-accounts.connect', $provider) }}" class="text-sm underline text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-200">
                                        {{ __('Connect') }}
                                    </a>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
