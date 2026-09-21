<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center px-4 py-2 bg-brand border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-brand/90 focus:bg-brand/90 active:bg-brand/80 focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2 focus:ring-offset-bg-surface transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
