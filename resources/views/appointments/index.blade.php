<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Appointments') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    @if (session('status'))
                        <div class="mb-4 text-sm text-green-600 dark:text-green-400">
                            {{ session('status') }}
                        </div>
                    @endif

                    @if ($appointments->isEmpty())
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ __('No appointments yet.') }}
                        </p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                <thead>
                                    <tr class="text-left text-gray-500 dark:text-gray-400">
                                        <th class="py-2 pr-4 font-medium">{{ __('Contact') }}</th>
                                        <th class="py-2 pr-4 font-medium">{{ __('Calendar') }}</th>
                                        <th class="py-2 pr-4 font-medium">{{ __('Date/Time') }}</th>
                                        <th class="py-2 pr-4 font-medium">{{ __('Status') }}</th>
                                        <th class="py-2 pr-4 font-medium text-right">{{ __('Actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    @php
                                        $statusColors = [
                                            'requested' => 'bg-yellow-100 dark:bg-yellow-900/40 text-yellow-700 dark:text-yellow-300',
                                            'booked' => 'bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300',
                                            'confirmed' => 'bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300',
                                            'cancelled' => 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300',
                                        ];
                                    @endphp

                                    @foreach ($appointments as $appointment)
                                        @php
                                            $timezone = $appointment->calendar->timezone
                                                ?? $appointment->calendar->location->timezone
                                                ?? 'UTC';
                                            $localStart = $appointment->starts_at->copy()->setTimezone($timezone);
                                        @endphp
                                        <tr>
                                            <td class="py-2 pr-4">
                                                {{ trim($appointment->contact->first_name.' '.$appointment->contact->last_name) }}
                                            </td>
                                            <td class="py-2 pr-4">{{ $appointment->calendar->name }}</td>
                                            <td class="py-2 pr-4">
                                                {{ $localStart->format('M j, Y g:i A') }} ({{ $timezone }})
                                            </td>
                                            <td class="py-2 pr-4">
                                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $statusColors[$appointment->status] ?? $statusColors['cancelled'] }}">
                                                    {{ ucfirst($appointment->status) }}
                                                </span>
                                            </td>
                                            <td class="py-2 pr-4 text-right whitespace-nowrap">
                                                @if (! in_array($appointment->status, ['confirmed', 'cancelled'], true))
                                                    <form method="POST" action="{{ route('appointments.confirm', $appointment) }}" class="inline">
                                                        @csrf
                                                        <button type="submit" class="underline text-green-600 dark:text-green-400 hover:text-green-900 dark:hover:text-green-200">
                                                            {{ __('Confirm') }}
                                                        </button>
                                                    </form>
                                                @endif

                                                @if ($appointment->status !== 'cancelled')
                                                    <form method="POST" action="{{ route('appointments.cancel', $appointment) }}" class="inline"
                                                            onsubmit="return confirm('{{ __('Cancel this appointment?') }}');">
                                                        @csrf
                                                        <button type="submit" class="ms-3 underline text-red-600 dark:text-red-400 hover:text-red-900 dark:hover:text-red-200">
                                                            {{ __('Cancel') }}
                                                        </button>
                                                    </form>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-4">
                            {{ $appointments->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
