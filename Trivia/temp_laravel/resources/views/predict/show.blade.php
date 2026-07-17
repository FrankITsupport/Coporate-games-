<x-event-layout :title="$game->name . ' — Predictions'">
    <div
        id="prediction-form"
        data-initial="{{ json_encode($initialState) }}"
        data-game-code="{{ $game->code }}"
        data-predict-url-template="{{ url('/predict/' . $game->code . '/prompts/__ID__/answer') }}"
        data-csrf="{{ csrf_token() }}"
    ></div>
</x-event-layout>
