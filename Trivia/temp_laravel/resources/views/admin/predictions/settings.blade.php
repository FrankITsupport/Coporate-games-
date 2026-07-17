<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Prediction Settings — {{ $game->name }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-3xl space-y-6 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded bg-green-50 px-4 py-2 text-sm text-green-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded bg-red-50 px-4 py-2 text-sm text-red-800">
                    <ul class="list-disc space-y-1 pl-4">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Match settings --}}
            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <h3 class="font-medium text-gray-800">Match Settings</h3>
                <form method="POST" action="{{ route('admin.games.predictions.settings.update', $game) }}" class="mt-4 space-y-4">
                    @csrf
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="team_a_name" value="Team A name (internal)" />
                            <x-text-input id="team_a_name" name="team_a_name" type="text" class="mt-1 block w-full" value="{{ old('team_a_name', $setting->team_a_name) }}" required />
                        </div>
                        <div>
                            <x-input-label for="team_b_name" value="Team B name (internal)" />
                            <x-text-input id="team_b_name" name="team_b_name" type="text" class="mt-1 block w-full" value="{{ old('team_b_name', $setting->team_b_name) }}" required />
                        </div>
                        <div>
                            <x-input-label for="team_a_flag" value="Team A flag (emoji)" />
                            <x-text-input id="team_a_flag" name="team_a_flag" type="text" class="mt-1 block w-full" value="{{ old('team_a_flag', $setting->team_a_flag) }}" placeholder="🏴" />
                        </div>
                        <div>
                            <x-input-label for="team_b_flag" value="Team B flag (emoji)" />
                            <x-text-input id="team_b_flag" name="team_b_flag" type="text" class="mt-1 block w-full" value="{{ old('team_b_flag', $setting->team_b_flag) }}" placeholder="🇦🇷" />
                        </div>
                        <div>
                            <x-input-label for="kickoff_at" value="Kick-off time" />
                            <x-text-input id="kickoff_at" name="kickoff_at" type="datetime-local" class="mt-1 block w-full" value="{{ old('kickoff_at', $setting->kickoff_at?->format('Y-m-d\TH:i')) }}" />
                        </div>
                        <div>
                            <x-input-label for="prediction_lock_at" value="Prediction lock time" />
                            <x-text-input id="prediction_lock_at" name="prediction_lock_at" type="datetime-local" class="mt-1 block w-full" value="{{ old('prediction_lock_at', $setting->prediction_lock_at?->format('Y-m-d\TH:i')) }}" />
                        </div>
                        <div>
                            <x-input-label for="winner_rule" value="Winner rule" />
                            <select id="winner_rule" name="winner_rule" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                                <option value="points" @selected(old('winner_rule', $setting->winner_rule) === 'points')>Points</option>
                                <option value="closest_score" @selected(old('winner_rule', $setting->winner_rule) === 'closest_score')>Closest score</option>
                            </select>
                        </div>
                    </div>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="allow_prediction_edits" value="1" {{ old('allow_prediction_edits', $setting->allow_prediction_edits) ? 'checked' : '' }}>
                        Allow players to edit predictions before lock
                    </label>
                    <x-primary-button>Save Settings</x-primary-button>
                </form>
            </div>

            {{-- Official results --}}
            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <h3 class="font-medium text-gray-800">Official Match Results</h3>
                <p class="mt-1 text-sm text-gray-500">Enter after the match, then calculate winners from the live control panel.</p>
                <form method="POST" action="{{ route('admin.games.predictions.results', $game) }}" class="mt-4 space-y-4">
                    @csrf
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="official_team_a_score" value="{{ $setting->team_a_name }} score" />
                            <x-text-input id="official_team_a_score" name="official_team_a_score" type="number" min="0" class="mt-1 block w-full" value="{{ old('official_team_a_score', $setting->official_team_a_score) }}" />
                        </div>
                        <div>
                            <x-input-label for="official_team_b_score" value="{{ $setting->team_b_name }} score" />
                            <x-text-input id="official_team_b_score" name="official_team_b_score" type="number" min="0" class="mt-1 block w-full" value="{{ old('official_team_b_score', $setting->official_team_b_score) }}" />
                        </div>
                        <div>
                            <x-input-label for="official_outcome" value="Outcome" />
                            <select id="official_outcome" name="official_outcome" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                                <option value="">—</option>
                                <option value="team_a_win" @selected(old('official_outcome', $setting->official_outcome) === 'team_a_win')>{{ $setting->team_a_name }} win</option>
                                <option value="draw" @selected(old('official_outcome', $setting->official_outcome) === 'draw')>Draw</option>
                                <option value="team_b_win" @selected(old('official_outcome', $setting->official_outcome) === 'team_b_win')>{{ $setting->team_b_name }} win</option>
                            </select>
                        </div>
                        <div>
                            <x-input-label for="official_first_goal_minute" value="First goal minute" />
                            <x-text-input id="official_first_goal_minute" name="official_first_goal_minute" type="number" min="0" class="mt-1 block w-full" value="{{ old('official_first_goal_minute', $setting->official_first_goal_minute) }}" />
                        </div>
                        <div>
                            <x-input-label for="official_first_goalscorer_id" value="First goalscorer" />
                            <select id="official_first_goalscorer_id" name="official_first_goalscorer_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                                <option value="">—</option>
                                @foreach ($roster as $player)
                                    <option value="{{ $player->id }}" @selected((int) old('official_first_goalscorer_id', $setting->official_first_goalscorer_id) === $player->id)>{{ $player->display_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="official_player_of_match_id" value="Player of the Match" />
                            <select id="official_player_of_match_id" name="official_player_of_match_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                                <option value="">—</option>
                                @foreach ($roster as $player)
                                    <option value="{{ $player->id }}" @selected((int) old('official_player_of_match_id', $setting->official_player_of_match_id) === $player->id)>{{ $player->display_name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <x-primary-button>Save Official Results</x-primary-button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
