<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Edit Funnel') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <form method="POST" action="{{ route('funnels.update', $funnel) }}">
                        @csrf
                        @method('PUT')

                        @include('funnels._form')

                        <div class="flex items-center justify-end mt-6">
                            <a href="{{ route('funnels.index') }}" class="text-sm text-gray-600 dark:text-gray-400 underline hover:text-gray-900 dark:hover:text-gray-100 me-4">
                                {{ __('Cancel') }}
                            </a>

                            <x-primary-button>
                                {{ __('Save Funnel') }}
                            </x-primary-button>
                        </div>
                    </form>
                </div>
            </div>

            @php
                $embedSnippet = '<script src="'.asset('embed.js').'" data-slug="'.$funnel->slug.'"></script>';
            @endphp

            <div class="mt-6 bg-bg-surface overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-text-primary"
                        x-data="{
                            copied: false,
                            copy() {
                                const el = this.$refs.snippet;
                                const done = () => { this.copied = true; setTimeout(() => this.copied = false, 2000); };
                                // navigator.clipboard only exists on HTTPS/localhost;
                                // fall back to select + execCommand elsewhere.
                                if (navigator.clipboard && window.isSecureContext) {
                                    navigator.clipboard.writeText(el.value).then(done);
                                } else {
                                    el.select();
                                    document.execCommand('copy');
                                    done();
                                }
                            }
                        }">
                    <h3 class="text-lg font-medium">{{ __('Embed on your website') }}</h3>
                    <p class="mt-1 text-sm text-text-secondary">
                        {{ __('Paste this snippet into your website\'s HTML where you want the offer form to appear.') }}
                    </p>

                    @unless ($funnel->is_published)
                        <p class="mt-2 text-sm text-status-warning">
                            {{ __('This funnel is not published yet, so the embedded form will not load until you publish it.') }}
                        </p>
                    @endunless

                    <textarea x-ref="snippet" readonly rows="2"
                            class="mt-4 block w-full font-mono text-sm bg-bg-base text-text-primary border-text-secondary/30 rounded-md"
                            @focus="$el.select()">{{ $embedSnippet }}</textarea>

                    <div class="mt-3 flex items-center gap-3">
                        <x-primary-button type="button" @click="copy()">
                            {{ __('Copy') }}
                        </x-primary-button>
                        <span x-show="copied" x-cloak class="text-sm text-status-success">{{ __('Copied!') }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
