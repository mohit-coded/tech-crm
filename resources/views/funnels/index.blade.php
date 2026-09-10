<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Funnels') }}
            </h2>

            <a href="{{ route('funnels.create') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gray-800 uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-white focus:bg-gray-700 dark:focus:bg-white active:bg-gray-900 dark:active:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                {{ __('New Funnel') }}
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

                    @if ($funnels->isEmpty())
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ __('No funnels yet.') }}
                        </p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                <thead>
                                    <tr class="text-left text-gray-500 dark:text-gray-400">
                                        <th class="py-2 pr-4 font-medium">{{ __('Name') }}</th>
                                        <th class="py-2 pr-4 font-medium">{{ __('Public URL') }}</th>
                                        <th class="py-2 pr-4 font-medium">{{ __('Status') }}</th>
                                        <th class="py-2 pr-4 font-medium text-right">{{ __('Actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach ($funnels as $funnel)
                                        <tr>
                                            <td class="py-2 pr-4">{{ $funnel->name }}</td>
                                            <td class="py-2 pr-4">
                                                <a href="{{ url('/f/'.$funnel->slug) }}" target="_blank" class="underline text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-200">
                                                    /f/{{ $funnel->slug }}
                                                </a>
                                            </td>
                                            <td class="py-2 pr-4">
                                                @if ($funnel->is_published)
                                                    <span class="inline-flex items-center rounded-full bg-green-100 dark:bg-green-900/40 px-2 py-0.5 text-xs font-medium text-green-700 dark:text-green-300">
                                                        {{ __('Published') }}
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center rounded-full bg-gray-100 dark:bg-gray-700 px-2 py-0.5 text-xs font-medium text-gray-600 dark:text-gray-300">
                                                        {{ __('Draft') }}
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="py-2 pr-4 text-right whitespace-nowrap">
                                                <a href="{{ route('funnels.edit', $funnel) }}" class="underline text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-200">
                                                    {{ __('Edit') }}
                                                </a>

                                                <form method="POST" action="{{ route('funnels.destroy', $funnel) }}" class="inline"
                                                        onsubmit="return confirm('{{ __('Delete this funnel?') }}');">
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
                            {{ $funnels->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
