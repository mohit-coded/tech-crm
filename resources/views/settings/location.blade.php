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
        </div>
    </div>
</x-app-layout>
