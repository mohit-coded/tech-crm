<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Opportunities') }}
        </h2>
    </x-slot>

    <div
        class="py-12"
        x-data="{ moveError: null }"
        x-on:opportunity-move-failed.window="moveError = $event.detail.message; setTimeout(() => moveError = null, 4000)"
    >
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">

            <div
                x-show="moveError"
                x-text="moveError"
                style="display: none;"
                class="rounded-md bg-red-50 dark:bg-red-900/40 px-4 py-3 text-sm text-red-700 dark:text-red-300"
            ></div>

            @if (! $pipeline)
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-sm text-gray-500 dark:text-gray-400">
                        {{ __('No pipeline set up yet.') }}
                    </div>
                </div>
            @else
                <div class="flex gap-4 overflow-x-auto pb-4">
                    @foreach ($stages as $stage)
                        <div class="w-72 shrink-0 bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg">
                            <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                                <h3 class="font-medium text-gray-900 dark:text-gray-100">{{ $stage->name }}</h3>
                                <span class="text-xs font-medium text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-gray-700 rounded-full px-2 py-0.5">
                                    {{ $stage->opportunities->count() }}
                                </span>
                            </div>

                            <div
                                class="p-3 space-y-3 min-h-[4rem]"
                                data-stage-id="{{ $stage->id }}"
                                x-data="opportunityColumn()"
                            >
                                @foreach ($stage->opportunities as $opportunity)
                                    <div
                                        data-opportunity-id="{{ $opportunity->id }}"
                                        class="cursor-move rounded-md border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/40 p-3 text-sm shadow-sm"
                                    >
                                        <div class="font-medium text-gray-900 dark:text-gray-100">{{ $opportunity->name }}</div>
                                        <div class="text-gray-500 dark:text-gray-400">
                                            {{ $opportunity->contact->first_name }} {{ $opportunity->contact->last_name }}
                                        </div>
                                        <div class="mt-1 font-medium text-gray-700 dark:text-gray-300">
                                            ${{ number_format($opportunity->monetary_value, 2) }}
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.2/Sortable.min.js"></script>
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('opportunityColumn', () => ({
                init() {
                    Sortable.create(this.$el, {
                        group: 'opportunities',
                        animation: 150,
                        onEnd: (evt) => {
                            if (evt.to !== evt.from) {
                                this.handleMove(evt);
                            }
                        },
                    });
                },
                async handleMove(evt) {
                    const card = evt.item;
                    const opportunityId = card.dataset.opportunityId;
                    const newStageId = evt.to.dataset.stageId;
                    const fromEl = evt.from;
                    const oldIndex = evt.oldIndex;

                    try {
                        const response = await fetch(`/api/opportunities/${opportunityId}/stage`, {
                            method: 'PATCH',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                            },
                            body: JSON.stringify({ pipeline_stage_id: newStageId }),
                        });

                        if (!response.ok) {
                            throw new Error('Move failed');
                        }
                    } catch (e) {
                        fromEl.insertBefore(card, fromEl.children[oldIndex] || null);
                        this.$dispatch('opportunity-move-failed', {
                            message: 'Could not move opportunity — please try again.',
                        });
                    }
                },
            }));
        });
    </script>
</x-app-layout>
