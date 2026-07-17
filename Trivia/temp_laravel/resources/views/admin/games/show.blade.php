<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $game->name }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-5xl space-y-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded bg-green-50 px-4 py-2 text-sm text-green-800">{{ session('status') }}</div>
            @endif

            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <dl class="grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
                    <div>
                        <dt class="text-gray-500">Code</dt>
                        <dd class="font-mono">{{ $game->code }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Type</dt>
                        <dd>{{ $game->event_type }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Status</dt>
                        <dd>{{ $game->status->value }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Questions</dt>
                        <dd>{{ $game->questions_count }}</dd>
                    </div>
                </dl>
                <div class="mt-6 flex gap-4">
                    <a href="{{ route('admin.games.control', $game) }}" class="rounded bg-brand-green px-3 py-1.5 text-sm text-white hover:bg-brand-lime">Live Control</a>
                    <a href="{{ route('admin.games.questions.index', $game) }}" class="rounded bg-brand-deep px-3 py-1.5 text-sm text-white hover:bg-brand-night">Manage Questions</a>
                    <a href="{{ route('admin.games.players.index', $game) }}" class="rounded bg-brand-deep px-3 py-1.5 text-sm text-white hover:bg-brand-night">Manage Players</a>
                    <a href="{{ route('admin.games.results.show', $game) }}" class="rounded bg-brand-deep px-3 py-1.5 text-sm text-white hover:bg-brand-night">Results & Export</a>
                    <a href="{{ route('admin.games.review.index', $game) }}" class="rounded bg-brand-deep px-3 py-1.5 text-sm text-white hover:bg-brand-night">Review Answers</a>
                    <a href="{{ route('admin.games.roster.index', $game) }}" class="rounded bg-brand-visa px-3 py-1.5 text-sm text-white hover:bg-blue-800">Roster</a>
                    <a href="{{ route('admin.games.predictions.settings', $game) }}" class="rounded bg-brand-visa px-3 py-1.5 text-sm text-white hover:bg-blue-800">Prediction Settings</a>
                    <a href="{{ route('admin.games.prediction-prompts.index', $game) }}" class="rounded bg-brand-visa px-3 py-1.5 text-sm text-white hover:bg-blue-800">Prediction Prompts</a>
                    <a href="{{ route('admin.games.edit', $game) }}" class="self-center text-sm underline">Edit Game</a>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
