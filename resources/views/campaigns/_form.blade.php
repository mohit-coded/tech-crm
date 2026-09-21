<div>
    <x-input-label for="name" :value="__('Name')" />
    <x-text-input id="name" class="block mt-1 w-full" type="text" name="name"
            :value="old('name', $campaign->name ?? '')" required autofocus />
    <x-input-error :messages="$errors->get('name')" class="mt-2" />
</div>

@php
    // trigger_event is a location-independent string, either
    // "opportunity_stage:{stage name}" (see EnrollContactsOnStageEntry)
    // or the fixed "appointment_completed" (see
    // EnrollContactsOnAppointmentCompletion). $stageNames is the
    // distinct set of pipeline stage names that exist across the
    // current location's pipelines, passed in by CampaignController.
    $currentTriggerEvent = old('trigger_event', $campaign->trigger_event ?? '');
    $stageTriggerOptions = $stageNames->mapWithKeys(fn ($name) => ["opportunity_stage:{$name}" => $name]);
    $hasStageOptions = $stageTriggerOptions->isNotEmpty();

    // The appointment-completion trigger doesn't depend on any pipeline
    // existing, so it's always offered, unlike the per-stage options.
    $triggerOptions = collect(['appointment_completed' => __('When an appointment is marked completed')])
        ->merge($stageTriggerOptions);

    // If the campaign's current value doesn't match any known option
    // (e.g. a stage was renamed/deleted since, or this is Stage 1 data
    // predating this dropdown), keep it selectable so saving the form
    // unchanged doesn't silently overwrite it with a different trigger.
    if ($currentTriggerEvent !== '' && ! $triggerOptions->has($currentTriggerEvent)) {
        $triggerOptions->prepend($currentTriggerEvent, $currentTriggerEvent);
    }
@endphp

<div class="mt-4">
    <x-input-label for="trigger_event" :value="__('Trigger Event')" />

    <select id="trigger_event" name="trigger_event"
            class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
            required>
        <option value="" disabled @selected($currentTriggerEvent === '')>{{ __('Select a trigger…') }}</option>
        @foreach ($triggerOptions as $value => $label)
            <option value="{{ $value }}" @selected($currentTriggerEvent === $value)>
                {{ $label }}
            </option>
        @endforeach
    </select>

    @unless ($hasStageOptions)
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            {{ __('No pipeline stages exist yet for this location — only the appointment-completion trigger is available. Create a pipeline with stages to trigger on stage entry too.') }}
        </p>
    @else
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            {{ __('Contacts are enrolled automatically when this trigger occurs.') }}
        </p>
    @endunless
    <x-input-error :messages="$errors->get('trigger_event')" class="mt-2" />
</div>

<div class="mt-4 flex items-center">
    <input id="is_active" type="checkbox" name="is_active" value="1"
            class="rounded dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:focus:ring-indigo-600 dark:focus:ring-offset-gray-800"
            @checked(old('is_active', $campaign->is_active ?? true))>
    <label for="is_active" class="ms-2 text-sm text-gray-600 dark:text-gray-400">
        {{ __('Active') }}
    </label>
    <x-input-error :messages="$errors->get('is_active')" class="mt-2" />
</div>
