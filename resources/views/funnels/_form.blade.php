<div>
    <x-input-label for="name" :value="__('Name')" />
    <x-text-input id="name" class="block mt-1 w-full" type="text" name="name"
            :value="old('name', $funnel->name ?? '')" required autofocus />
    <x-input-error :messages="$errors->get('name')" class="mt-2" />
</div>

<div class="mt-4">
    <x-input-label for="slug" :value="__('Slug')" />
    <x-text-input id="slug" class="block mt-1 w-full" type="text" name="slug"
            :value="old('slug', $funnel->slug ?? '')" required />
    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
        {{ __('Public URL:') }} {{ url('/f') }}/{{ old('slug', $funnel->slug ?? '') }}
    </p>
    <x-input-error :messages="$errors->get('slug')" class="mt-2" />
</div>

<div class="mt-4">
    <x-input-label for="headline" :value="__('Headline')" />
    <x-text-input id="headline" class="block mt-1 w-full" type="text" name="headline"
            :value="old('headline', $funnel->headline ?? '')" required />
    <x-input-error :messages="$errors->get('headline')" class="mt-2" />
</div>

<div class="mt-4">
    <x-input-label for="subheadline" :value="__('Subheadline')" />
    <x-text-input id="subheadline" class="block mt-1 w-full" type="text" name="subheadline"
            :value="old('subheadline', $funnel->subheadline ?? '')" />
    <x-input-error :messages="$errors->get('subheadline')" class="mt-2" />
</div>

<div class="mt-4">
    <x-input-label for="button_text" :value="__('Button Text')" />
    <x-text-input id="button_text" class="block mt-1 w-full" type="text" name="button_text"
            :value="old('button_text', $funnel->button_text ?? 'Claim Offer')" required />
    <x-input-error :messages="$errors->get('button_text')" class="mt-2" />
</div>

<div class="mt-4">
    <x-input-label for="calendar_id" :value="__('Booking Calendar')" />
    <select id="calendar_id" name="calendar_id"
            class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
        <option value="">{{ __('None') }}</option>
        @foreach ($calendars as $calendar)
            <option value="{{ $calendar->id }}" @selected((int) old('calendar_id', $funnel->calendar_id ?? '') === $calendar->id)>
                {{ $calendar->name }}
            </option>
        @endforeach
    </select>
    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
        {{ __('Leads captured by this funnel can book into this calendar.') }}
    </p>
    <x-input-error :messages="$errors->get('calendar_id')" class="mt-2" />
</div>

<div class="mt-4 flex items-center">
    <input id="is_published" type="checkbox" name="is_published" value="1"
            class="rounded dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:focus:ring-indigo-600 dark:focus:ring-offset-gray-800"
            @checked(old('is_published', $funnel->is_published ?? false))>
    <label for="is_published" class="ms-2 text-sm text-gray-600 dark:text-gray-400">
        {{ __('Published (visible to the public)') }}
    </label>
    <x-input-error :messages="$errors->get('is_published')" class="mt-2" />
</div>
