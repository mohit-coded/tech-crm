<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <!-- Stat cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
                            {{ __('Open Opportunities') }}
                        </div>
                        <div class="mt-1 text-3xl font-semibold">
                            {{ $openCount }}
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
                            {{ __('Pipeline Value') }}
                        </div>
                        <div class="mt-1 text-3xl font-semibold">
                            ${{ number_format($pipelineValue, 2) }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stage breakdown -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h3 class="text-lg font-medium mb-4">{{ __('Open Opportunities by Stage') }}</h3>

                    @if ($stageBreakdown->isEmpty())
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ __('No open opportunities yet.') }}
                        </p>
                    @else
                        <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach ($stageBreakdown as $stage)
                                <li class="py-2 flex items-center justify-between text-sm">
                                    <span>{{ $stage->stage_name }}</span>
                                    <span class="font-medium">{{ $stage->opportunities_count }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>

            <!-- Recent opportunities -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h3 class="text-lg font-medium mb-4">{{ __('Recent Opportunities') }}</h3>

                    @if ($recentOpportunities->isEmpty())
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ __('No opportunities yet.') }}
                        </p>
                    @else
                        <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach ($recentOpportunities as $opportunity)
                                <li class="py-2 flex items-center justify-between text-sm">
                                    <div>
                                        <div class="font-medium">{{ $opportunity->name }}</div>
                                        <div class="text-gray-500 dark:text-gray-400">
                                            {{ $opportunity->contact->first_name }} {{ $opportunity->contact->last_name }}
                                            &middot; {{ $opportunity->stage->name }}
                                        </div>
                                    </div>
                                    <span class="font-medium">${{ number_format($opportunity->monetary_value, 2) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
