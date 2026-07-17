<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Results - {{ $game->name }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded bg-green-50 px-4 py-2 text-sm text-green-800">{{ session('status') }}</div>
            @endif

            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h3 class="text-2xl font-bold text-gray-900">{{ $game->name }}</h3>
                    <p class="text-gray-500">Game Code: {{ $game->code }}</p>
                </div>
                <div class="flex gap-3">
                    <a href="{{ route('admin.games.results.export.trivia', $game) }}" class="rounded bg-brand-green px-4 py-2 text-sm text-white hover:bg-brand-lime">
                        Export Trivia CSV
                    </a>
                    <a href="{{ route('admin.games.results.export.predictions', $game) }}" class="rounded bg-brand-visa px-4 py-2 text-sm text-white hover:bg-blue-800">
                        Export Predictions CSV
                    </a>
                    <a href="{{ route('admin.games.show', $game) }}" class="rounded bg-white px-4 py-2 text-sm text-gray-900 border border-gray-300 hover:bg-gray-50">
                        Back to Game
                    </a>
                </div>
            </div>

            <div class="overflow-hidden bg-white shadow sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Rank</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Nickname</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Phone</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Email</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Score</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Correct</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Incorrect</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Missed</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">2× Correct</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Avg Time</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Streak</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        @foreach ($players as $player)
                            <tr>
                                <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">
                                    {{ $player->current_rank ?? '—' }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-900">
                                    {{ $player->nickname }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                    {{ $player->phone }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                    {{ $player->email ?? '—' }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm font-bold text-gray-900">
                                    {{ $player->total_score ?? 0 }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                    {{ $player->correct_answers ?? 0 }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                    {{ $player->incorrect_answers ?? 0 }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                    {{ $player->missed_answers ?? 0 }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                    {{ $player->double_point_correct_answers ?? 0 }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                    {{ $player->correct_answers > 0 
                                        ? round($player->total_correct_response_ms / $player->correct_answers) . 'ms' 
                                        : '—' }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                    {{ $player->current_streak ?? 0 }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm">
                                    @if ($player->disqualified_at)
                                        <span class="inline-flex rounded-full bg-red-100 px-2 text-xs font-semibold leading-5 text-red-800">Disqualified</span>
                                    @else
                                        <span class="inline-flex rounded-full bg-green-100 px-2 text-xs font-semibold leading-5 text-green-800">Active</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-6 text-sm text-gray-500">
                <p>Total Players: {{ $players->count() }}</p>
                <p>Active Players: {{ $players->where('disqualified_at', null)->count() }}</p>
                <p>Disqualified: {{ $players->where('disqualified_at', '!=', null)->count() }}</p>
            </div>
        </div>
    </div>
</x-app-layout>
