<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <!-- Range selector -->
            <div class="flex items-center justify-end gap-2 px-4 sm:px-0">
                @foreach (['7d' => __('7d'), '30d' => __('30d'), '90d' => __('90d'), 'all' => __('All')] as $value => $label)
                    <a href="{{ route('dashboard', ['range' => $value]) }}"
                            class="px-3 py-1.5 text-sm font-medium rounded-md {{ $range === $value ? 'bg-indigo-600 text-white' : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>

            <!-- Stat cards -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
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

                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
                            {{ __('Conversion Rate') }}
                        </div>
                        <div class="mt-1 text-3xl font-semibold">
                            {{ $conversionRate }}%
                        </div>
                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            {{ $wonInRange }} {{ __('won') }} / {{ $totalInRange }} {{ __('total') }}
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
