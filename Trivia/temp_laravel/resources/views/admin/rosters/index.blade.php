<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Prediction Roster — {{ $game->name }}</h2>
            <form method="POST" action="{{ route('admin.games.roster.seed', $game) }}">
                @csrf
                <button class="rounded bg-gray-900 px-3 py-1.5 text-sm text-white">Seed Default Rosters</button>
            </form>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded bg-green-50 px-4 py-2 text-sm text-green-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded bg-red-50 px-4 py-2 text-sm text-red-800">{{ $errors->first() }}</div>
            @endif

            @foreach ([[$teamAName, $teamA, $teamAName], [$teamBName, $teamB, $teamBName], ['Neutral (e.g. No goalscorer)', $neutral, null]] as [$label, $players, $teamNameValue])
                <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                    <h3 class="font-medium text-gray-800">{{ $label }}</h3>

                    <div class="mt-3 divide-y divide-gray-100">
                        @forelse ($players as $player)
                            <div class="flex items-center gap-3 py-2 text-sm">
                                <form method="POST" action="{{ route('admin.games.roster.update', [$game, $player]) }}" class="flex flex-1 items-center gap-2">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="team_name" value="{{ $player->team_name }}">
                                    <input type="text" name="display_name" value="{{ $player->display_name }}" class="flex-1 rounded border-gray-300 text-sm" required>
                                    <input type="text" name="shirt_number" value="{{ $player->shirt_number }}" placeholder="#" class="w-14 rounded border-gray-300 text-sm">
                                    <input type="text" name="position" value="{{ $player->position }}" placeholder="Pos" class="w-16 rounded border-gray-300 text-sm">
                                    <select name="squad_status" class="rounded border-gray-300 text-sm">
                                        @foreach (['starter', 'substitute', 'unknown', 'unavailable'] as $status)
                                            <option value="{{ $status }}" @selected($player->squad_status === $status)>{{ ucfirst($status) }}</option>
                                        @endforeach
                                    </select>
                                    <button class="rounded bg-gray-900 px-2 py-1 text-xs text-white">Save</button>
                                </form>
                                <form method="POST" action="{{ route('admin.games.roster.destroy', [$game, $player]) }}" onsubmit="return confirm('Remove this player?');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="rounded bg-red-600 px-2 py-1 text-xs text-white">Remove</button>
                                </form>
                            </div>
                        @empty
                            <p class="py-3 text-sm text-gray-400">No players yet.</p>
                        @endforelse
                    </div>

                    <form method="POST" action="{{ route('admin.games.roster.store', $game) }}" class="mt-3 flex items-center gap-2 border-t border-gray-100 pt-3 text-sm">
                        @csrf
                        <input type="hidden" name="team_name" value="{{ $teamNameValue }}">
                        <input type="text" name="display_name" placeholder="Player name" required class="flex-1 rounded border-gray-300 text-sm">
                        <input type="text" name="shirt_number" placeholder="#" class="w-14 rounded border-gray-300 text-sm">
                        <input type="text" name="position" placeholder="Pos" class="w-16 rounded border-gray-300 text-sm">
                        <select name="squad_status" class="rounded border-gray-300 text-sm">
                            <option value="starter">Starter</option>
                            <option value="substitute">Substitute</option>
                            <option value="unknown">Unknown</option>
                            <option value="unavailable">Unavailable</option>
                        </select>
                        <button class="rounded bg-green-600 px-3 py-1 text-xs text-white">Add</button>
                    </form>
                </div>
            @endforeach
        </div>
    </div>
</x-app-layout>
