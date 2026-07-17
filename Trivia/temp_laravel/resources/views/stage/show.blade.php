<x-event-layout :title="$game->name" :full-width="true">
    <div
        id="stage-screen"
        data-initial="{{ json_encode($initialState) }}"
        data-game-code="{{ $game->code }}"
    ></div>
</x-event-layout>
