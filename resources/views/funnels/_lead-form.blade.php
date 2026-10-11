{{-- Shared by funnels/public.blade.php and funnels/embed.blade.php. Both post
     to the same funnels.public.submit endpoint. $embed (default false) adds a
     hidden flag so store() redirects back into the iframe instead of to the
     full public page. --}}
<form method="POST" action="{{ route('funnels.public.submit', $funnel->slug) }}" class="mt-6 space-y-4">
    @csrf

    @if ($embed ?? false)
        <input type="hidden" name="embed" value="1">
    @endif

    <div>
        <x-input-label for="name" :value="__('Name')" />
        <x-text-input id="name" class="block mt-1 w-full" type="text" name="name"
                :value="old('name')" required :autofocus="! ($embed ?? false)" />
        <x-input-error :messages="$errors->get('name')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="email" :value="__('Email')" />
        <x-text-input id="email" class="block mt-1 w-full" type="email" name="email"
                :value="old('email')" required />
        <x-input-error :messages="$errors->get('email')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="phone" :value="__('Phone')" />
        <x-text-input id="phone" class="block mt-1 w-full" type="text" name="phone"
                :value="old('phone')" required />
        <x-input-error :messages="$errors->get('phone')" class="mt-2" />
    </div>

    <x-primary-button class="w-full justify-center">
        {{ $funnel->button_text }}
    </x-primary-button>
</form>
