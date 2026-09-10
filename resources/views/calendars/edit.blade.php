@php
    // Blank rows offered for adding new rules in one save. Any left fully
    // blank are ignored server-side rather than saved — see
    // CalendarController@syncAvailabilityRules.
    $newRuleRowCount = 3;
@endphp
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Edit Calendar') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('status'))
                <div class="text-sm text-green-600 dark:text-green-400">
                    {{ session('status') }}
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <form method="POST" action="{{ route('calendars.update', $calendar) }}">
                        @csrf
                        @method('PUT')

                        @include('calendars._form')

                        <h3 class="mt-8 mb-4 text-lg font-medium">{{ __('Availability Rules') }}</h3>

                        @if ($calendar->availabilityRules->isNotEmpty())
                            <div class="space-y-3">
                                @foreach ($calendar->availabilityRules as $rule)
                                    <div class="flex flex-wrap items-center gap-3">
                                        <select name="rules[{{ $rule->id }}][day_of_week]" class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                                            @foreach (\App\Models\AvailabilityRule::DAYS as $value => $label)
                                                <option value="{{ $value }}" @selected((int) old("rules.{$rule->id}.day_of_week", $rule->day_of_week) === $value)>
                                                    {{ $label }}
                                                </option>
                                            @endforeach
                                        </select>

                                        <input type="time" name="rules[{{ $rule->id }}][start_time]"
                                                value="{{ old("rules.{$rule->id}.start_time", $rule->start_time) }}"
                                                class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">

                                        <span class="text-sm text-gray-500 dark:text-gray-400">{{ __('to') }}</span>

                                        <input type="time" name="rules[{{ $rule->id }}][end_time]"
                                                value="{{ old("rules.{$rule->id}.end_time", $rule->end_time) }}"
                                                class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">

                                        <label class="flex items-center text-sm text-red-600 dark:text-red-400">
                                            <input type="checkbox" name="rules[{{ $rule->id }}][remove]" value="1"
                                                    class="rounded dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-red-600 shadow-sm focus:ring-red-500 dark:focus:ring-offset-gray-800 me-1">
                                            {{ __('Remove') }}
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                {{ __('No availability rules yet.') }}
                            </p>
                        @endif

                        <h4 class="mt-6 mb-3 text-sm font-medium text-gray-600 dark:text-gray-400">
                            {{ __('Add New Rule(s)') }}
                        </h4>

                        <div class="space-y-3">
                            @for ($i = 0; $i < $newRuleRowCount; $i++)
                                <div class="flex flex-wrap items-center gap-3">
                                    <select name="new_rules[{{ $i }}][day_of_week]" class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                                        <option value="">{{ __('— Day —') }}</option>
                                        @foreach (\App\Models\AvailabilityRule::DAYS as $value => $label)
                                            <option value="{{ $value }}" @selected(old("new_rules.{$i}.day_of_week") !== null && (int) old("new_rules.{$i}.day_of_week") === $value)>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>

                                    <input type="time" name="new_rules[{{ $i }}][start_time]"
                                            value="{{ old("new_rules.{$i}.start_time") }}"
                                            class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">

                                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ __('to') }}</span>

                                    <input type="time" name="new_rules[{{ $i }}][end_time]"
                                            value="{{ old("new_rules.{$i}.end_time") }}"
                                            class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                                </div>
                            @endfor
                        </div>

                        <x-input-error :messages="$errors->get('rules.*')" class="mt-2" />
                        <x-input-error :messages="$errors->get('new_rules.*')" class="mt-2" />

                        <div class="flex items-center justify-end mt-6">
                            <a href="{{ route('calendars.index') }}" class="text-sm text-gray-600 dark:text-gray-400 underline hover:text-gray-900 dark:hover:text-gray-100 me-4">
                                {{ __('Cancel') }}
                            </a>

                            <x-primary-button>
                                {{ __('Save Calendar') }}
                            </x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
