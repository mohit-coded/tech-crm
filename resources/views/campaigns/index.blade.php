<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Campaigns') }}
            </h2>

            <a href="{{ route('campaigns.create') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gray-800 uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-white focus:bg-gray-700 dark:focus:bg-white active:bg-gray-900 dark:active:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                {{ __('New Campaign') }}
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    @if (session('status'))
                        <div class="mb-4 text-sm text-green-600 dark:text-green-400">
                            {{ session('status') }}
                        </div>
                    @endif

                    @if ($campaigns->isEmpty())
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ __('No campaigns yet.') }}
                        </p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                <thead>
                                    <tr class="text-left text-gray-500 dark:text-gray-400">
                                        <th class="py-2 pr-4 font-medium">{{ __('Name') }}</th>
                                        <th class="py-2 pr-4 font-medium">{{ __('Trigger Event') }}</th>
                                        <th class="py-2 pr-4 font-medium">{{ __('Steps') }}</th>
                                        <th class="py-2 pr-4 font-medium">{{ __('Status') }}</th>
                                        <th class="py-2 pr-4 font-medium text-right">{{ __('Actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach ($campaigns as $campaign)
                                        <tr>
                                            <td class="py-2 pr-4">{{ $campaign->name }}</td>
                                            <td class="py-2 pr-4">{{ $campaign->trigger_event }}</td>
                                            <td class="py-2 pr-4">{{ $campaign->steps_count }}</td>
                                            <td class="py-2 pr-4">
                                                @if ($campaign->is_active)
                                                    <span class="inline-flex items-center rounded-full bg-green-100 dark:bg-green-900/40 px-2 py-0.5 text-xs font-medium text-green-700 dark:text-green-300">
                                                        {{ __('Active') }}
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center rounded-full bg-gray-100 dark:bg-gray-700 px-2 py-0.5 text-xs font-medium text-gray-600 dark:text-gray-300">
                                                        {{ __('Inactive') }}
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="py-2 pr-4 text-right whitespace-nowrap">
                                                <a href="{{ route('campaigns.edit', $campaign) }}" class="underline text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-200">
                                                    {{ __('Edit') }}
                                                </a>

                                                <form method="POST" action="{{ route('campaigns.destroy', $campaign) }}" class="inline"
                                                        onsubmit="return confirm('{{ __('Delete this campaign?') }}');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="ms-3 underline text-red-600 dark:text-red-400 hover:text-red-900 dark:hover:text-red-200">
                                                        {{ __('Delete') }}
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-4">
                            {{ $campaigns->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
