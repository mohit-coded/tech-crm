@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'border-text-secondary/30 bg-bg-base text-text-primary focus:border-brand focus:ring-brand rounded-md shadow-sm']) }}>
