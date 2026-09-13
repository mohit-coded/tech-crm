@php
    // Blank rows offered for adding new steps in one save. Any left
    // without a body are ignored server-side rather than saved — see
    // CampaignController@syncSteps.
    $newStepRowCount = 3;
@endphp
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Edit Campaign') }}
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
                    <form method="POST" action="{{ route('campaigns.update', $campaign) }}">
                        @csrf
                        @method('PUT')

                        @include('campaigns._form')

                        <h3 class="mt-8 mb-4 text-lg font-medium">{{ __('Steps') }}</h3>

                        @if ($campaign->steps->isNotEmpty())
                            <div class="space-y-3">
                                @foreach ($campaign->steps as $step)
                                    <div class="flex flex-wrap items-start gap-3">
                                        <select name="steps[{{ $step->id }}][channel]" class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                                            @foreach (['sms' => __('SMS'), 'email' => __('Email')] as $value => $label)
                                                <option value="{{ $value }}" @selected(old("steps.{$step->id}.channel", $step->channel) === $value)>
                                                    {{ $label }}
                                                </option>
                                            @endforeach
                                        </select>

                                        <x-text-input name="steps[{{ $step->id }}][body]" class="flex-1 min-w-64" type="text"
                                                placeholder="{{ __('Message body') }}"
                                                :value="old('steps.'.$step->id.'.body', $step->body)" />

                                        <div class="flex items-center gap-1">
                                            <x-text-input name="steps[{{ $step->id }}][delay_minutes]" class="w-24" type="number" min="0"
                                                    :value="old('steps.'.$step->id.'.delay_minutes', $step->delay_minutes)" />
                                            <span class="text-sm text-gray-500 dark:text-gray-400">{{ __('min delay') }}</span>
                                        </div>

                                        <label class="flex items-center text-sm text-red-600 dark:text-red-400">
                                            <input type="checkbox" name="steps[{{ $step->id }}][remove]" value="1"
                                                    class="rounded dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-red-600 shadow-sm focus:ring-red-500 dark:focus:ring-offset-gray-800 me-1">
                                            {{ __('Remove') }}
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                {{ __('No steps yet.') }}
                            </p>
                        @endif

                        <h4 class="mt-6 mb-3 text-sm font-medium text-gray-600 dark:text-gray-400">
                            {{ __('Add New Step(s)') }}
                        </h4>

                        <div class="space-y-3">
                            @for ($i = 0; $i < $newStepRowCount; $i++)
                                <div class="flex flex-wrap items-start gap-3">
                                    <select name="new_steps[{{ $i }}][channel]" class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                                        @foreach (['sms' => __('SMS'), 'email' => __('Email')] as $value => $label)
                                            <option value="{{ $value }}" @selected(old("new_steps.{$i}.channel") === $value)>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>

                                    <x-text-input name="new_steps[{{ $i }}][body]" class="flex-1 min-w-64" type="text"
                                            placeholder="{{ __('Message body') }}" :value="old('new_steps.'.$i.'.body')" />

                                    <div class="flex items-center gap-1">
                                        <x-text-input name="new_steps[{{ $i }}][delay_minutes]" class="w-24" type="number" min="0"
                                                :value="old('new_steps.'.$i.'.delay_minutes', 0)" />
                                        <span class="text-sm text-gray-500 dark:text-gray-400">{{ __('min delay') }}</span>
                                    </div>
                                </div>
                            @endfor
                        </div>

                        <x-input-error :messages="$errors->get('steps.*')" class="mt-2" />
                        <x-input-error :messages="$errors->get('new_steps.*')" class="mt-2" />

                        <div class="flex items-center justify-end mt-6">
                            <a href="{{ route('campaigns.index') }}" class="text-sm text-gray-600 dark:text-gray-400 underline hover:text-gray-900 dark:hover:text-gray-100 me-4">
                                {{ __('Cancel') }}
                            </a>

                            <x-primary-button>
                                {{ __('Save Campaign') }}
                            </x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
