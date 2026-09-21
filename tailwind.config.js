import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/**
 * Semantic color tokens (Frontend redesign, Stage A).
 *
 * Tailwind's theme.colors entries each resolve to a single value (or
 * a function) per key — there's no built-in `{ DEFAULT, dark }` shape
 * that automatically expands into a `dark:` variant the way `{
 * DEFAULT, 50, 100, ... }` expands into shade suffixes. Modeling
 * light/dark as two named colors (e.g. `brand` / `brand-dark`) would
 * work, but pushes the light/dark decision onto every call site —
 * every element needing the token would have to write
 * `bg-brand dark:bg-brand-dark`, everywhere, forever.
 *
 * Tailwind's own documented pattern for "one class name, automatically
 * correct in both modes" is to back each color with a CSS custom
 * property that itself changes value under `prefers-color-scheme:
 * dark` (defined in resources/css/app.css), and point the Tailwind
 * color at `rgb(var(--x) / <alpha-value>)`. The `<alpha-value>`
 * placeholder is substituted by Tailwind at build time with whatever
 * opacity modifier is used (`bg-brand/20` → alpha 0.2, plain `bg-brand`
 * → alpha 1), which is exactly what unlocks opacity utilities for a
 * CSS-variable-backed color — a plain `rgb(var(--x))` with no
 * `<alpha-value>` would ignore opacity modifiers entirely.
 *
 * Net effect: `bg-surface`, `text-primary`, `border-brand`, etc. are
 * dark-mode-aware on their own. No token here ever needs a `dark:`
 * prefix just to swap shades — `dark:` is still used normally for
 * anything that isn't just a color swap (e.g. a different shadow).
 */
const semanticColor = (name) => `rgb(var(--color-${name}) / <alpha-value>)`;

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    // Explicit, not just relying on the default: this app has no `.dark`
    // class toggle anywhere, and every existing `dark:` utility across
    // the whole app (not just this stage's pages) already assumes
    // OS-preference-driven dark mode. Switching to 'class' here would
    // silently break dark mode everywhere else.
    darkMode: 'media',

    theme: {
        extend: {
            colors: {
                'bg-base': semanticColor('bg-base'),
                'bg-surface': semanticColor('bg-surface'),
                'bg-nav': semanticColor('bg-nav'),
                'text-primary': semanticColor('text-primary'),
                'text-secondary': semanticColor('text-secondary'),
                brand: semanticColor('brand'),
                'status-success': semanticColor('status-success'),
                'status-warning': semanticColor('status-warning'),
                'status-danger': semanticColor('status-danger'),
                'status-info': semanticColor('status-info'),
            },
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms],
};
