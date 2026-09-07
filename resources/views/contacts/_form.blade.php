<div>
    <x-input-label for="first_name" :value="__('First Name')" />
    <x-text-input id="first_name" class="block mt-1 w-full" type="text" name="first_name"
            :value="old('first_name', $contact->first_name ?? '')" required autofocus />
    <x-input-error :messages="$errors->get('first_name')" class="mt-2" />
</div>

<div class="mt-4">
    <x-input-label for="last_name" :value="__('Last Name')" />
    <x-text-input id="last_name" class="block mt-1 w-full" type="text" name="last_name"
            :value="old('last_name', $contact->last_name ?? '')" />
    <x-input-error :messages="$errors->get('last_name')" class="mt-2" />
</div>

<div class="mt-4">
    <x-input-label for="email" :value="__('Email')" />
    <x-text-input id="email" class="block mt-1 w-full" type="email" name="email"
            :value="old('email', $contact->email ?? '')" />
    <x-input-error :messages="$errors->get('email')" class="mt-2" />
</div>

<div class="mt-4">
    <x-input-label for="phone" :value="__('Phone')" />
    <x-text-input id="phone" class="block mt-1 w-full" type="text" name="phone"
            :value="old('phone', $contact->phone ?? '')" />
    <x-input-error :messages="$errors->get('phone')" class="mt-2" />
</div>
