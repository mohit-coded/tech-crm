<div>
    <x-input-label for="name" :value="__('Name')" />
    <x-text-input id="name" class="block mt-1 w-full" type="text" name="name"
            :value="old('name', $campaign->name ?? '')" required autofocus />
    <x-input-error :messages="$errors->get('name')" class="mt-2" />
</div>

<div class="mt-4">
    <x-input-label for="trigger_event" :value="__('Trigger Event')" />
    <x-text-input id="trigger_event" class="block mt-1 w-full" type="text" name="trigger_event"
            placeholder="opportunity_stage_changed" :value="old('trigger_event', $campaign->trigger_event ?? '')" required />
    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
        {{ __('The event name that will start enrollment once the execution engine exists (not wired up yet).') }}
    </p>
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
