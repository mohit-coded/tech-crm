<div>
    <x-input-label for="name" :value="__('Name')" />
    <x-text-input id="name" class="block mt-1 w-full" type="text" name="name"
            :value="old('name', $calendar->name ?? '')" required autofocus />
    <x-input-error :messages="$errors->get('name')" class="mt-2" />
</div>

<div class="mt-4">
    <x-input-label for="duration_minutes" :value="__('Appointment Duration (minutes)')" />
    <x-text-input id="duration_minutes" class="block mt-1 w-full" type="number" min="5" max="480" name="duration_minutes"
            :value="old('duration_minutes', $calendar->duration_minutes ?? 30)" required />
    <x-input-error :messages="$errors->get('duration_minutes')" class="mt-2" />
</div>

<div class="mt-4">
    <x-input-label for="timezone" :value="__('Timezone')" />
    <x-text-input id="timezone" class="block mt-1 w-full" type="text" name="timezone"
            placeholder="America/New_York" :value="old('timezone', $calendar->timezone ?? '')" />
    <x-input-error :messages="$errors->get('timezone')" class="mt-2" />
</div>

<div class="mt-4 flex items-center">
    <input id="is_active" type="checkbox" name="is_active" value="1"
            class="rounded dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:focus:ring-indigo-600 dark:focus:ring-offset-gray-800"
            @checked(old('is_active', $calendar->is_active ?? true))>
    <label for="is_active" class="ms-2 text-sm text-gray-600 dark:text-gray-400">
        {{ __('Active') }}
    </label>
    <x-input-error :messages="$errors->get('is_active')" class="mt-2" />
</div>
