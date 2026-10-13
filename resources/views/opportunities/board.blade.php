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
                                        x-data="opportunityCard({{ $opportunity->id }})"
                                        :class="status !== 'open' && 'js-no-drag opacity-60 cursor-default'"
                                        class="cursor-move rounded-md border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/40 p-3 text-sm shadow-sm"
                                    >
                                        <div class="font-medium text-gray-900 dark:text-gray-100">{{ $opportunity->name }}</div>
                                        <div class="text-gray-500 dark:text-gray-400">
                                            {{ $opportunity->contact->first_name }} {{ $opportunity->contact->last_name }}
                                        </div>
                                        <div class="mt-1 font-medium text-gray-700 dark:text-gray-300">
                                            ${{ number_format($opportunity->monetary_value, 2) }}
                                        </div>

                                        {{-- Closing a deal is separate from moving stages. A
                                             closed card stays put (dimmed, no longer draggable)
                                             until reload; the board only loads open
                                             opportunities, so it's gone after that. --}}
                                        <div class="mt-2 flex items-center gap-2">
                                            <template x-if="status === 'open'">
                                                <div class="flex gap-2">
                                                    <button type="button" x-on:click="setStatus('won')" :disabled="saving"
                                                            class="js-no-drag rounded px-2 py-0.5 text-xs font-medium text-status-success border border-status-success/40 hover:bg-status-success/10 disabled:opacity-50">
                                                        {{ __('Won') }}
                                                    </button>
                                                    <button type="button" x-on:click="setStatus('lost')" :disabled="saving"
                                                            class="js-no-drag rounded px-2 py-0.5 text-xs font-medium text-status-danger border border-status-danger/40 hover:bg-status-danger/10 disabled:opacity-50">
                                                        {{ __('Lost') }}
                                                    </button>
                                                </div>
                                            </template>
                                            <span x-show="status === 'won'" style="display: none;"
                                                    class="rounded-full px-2 py-0.5 text-xs font-medium bg-status-success/15 text-status-success">
                                                {{ __('Won') }}
                                            </span>
                                            <span x-show="status === 'lost'" style="display: none;"
                                                    class="rounded-full px-2 py-0.5 text-xs font-medium bg-status-danger/15 text-status-danger">
                                                {{ __('Lost') }}
                                            </span>
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
                        // Won/Lost buttons, and cards already closed, never
                        // start a drag. preventOnFilter: false so the
                        // buttons' own click events still fire.
                        filter: '.js-no-drag',
                        preventOnFilter: false,
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

            Alpine.data('opportunityCard', (opportunityId) => ({
                status: 'open',
                saving: false,
                async setStatus(newStatus) {
                    this.saving = true;

                    try {
                        const response = await fetch(`/api/opportunities/${opportunityId}/status`, {
                            method: 'PATCH',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                            },
                            body: JSON.stringify({ status: newStatus }),
                        });

                        if (!response.ok) {
                            throw new Error('Status update failed');
                        }

                        this.status = newStatus;
                    } catch (e) {
                        this.$dispatch('opportunity-move-failed', {
                            message: 'Could not update opportunity — please try again.',
                        });
                    } finally {
                        this.saving = false;
                    }
                },
            }));
        });
    </script>
</x-app-layout>
