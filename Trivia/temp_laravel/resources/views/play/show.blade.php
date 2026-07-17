<x-event-layout :title="$game->name">
    <div
        id="player-game"
        data-initial="{{ json_encode($initialState) }}"
        data-game-code="{{ $game->code }}"
        data-question-answer-url-template="{{ url('/play/' . $game->code . '/questions/__ID__/answer') }}"
        data-csrf="{{ csrf_token() }}"
    ></div>
</x-event-layout>
