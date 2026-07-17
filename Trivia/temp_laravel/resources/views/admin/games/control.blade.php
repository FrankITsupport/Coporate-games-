@php
    use App\Enums\GameStatus;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Live Control — {{ $game->name }}</h2>
            <span class="rounded-full bg-green-100 px-3 py-1 text-xs font-medium text-brand-deep">{{ $game->status->value }}</span>
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

            {{-- Registration --}}
            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <h3 class="font-medium text-gray-800">Registration</h3>
                <p class="mt-1 text-sm text-gray-500">
                    @if ($game->registration_opened_at)
                        Opened {{ $game->registration_opened_at->diffForHumans() }}.
                    @else
                        Not opened yet.
                    @endif
                </p>
                <div class="mt-3 flex gap-3">
                    <form method="POST" action="{{ route('admin.games.registration.open', $game) }}">
                        @csrf
                        <button class="rounded bg-gray-900 px-3 py-1.5 text-sm text-white disabled:opacity-30"
                            @unless (in_array($game->status, [GameStatus::Draft, GameStatus::Paused], true)) disabled @endunless>
                            Open Registration
                        </button>
                    </form>
                    <form method="POST" action="{{ route('admin.games.registration.close', $game) }}">
                        @csrf
                        <button class="rounded bg-gray-200 px-3 py-1.5 text-sm text-gray-800 disabled:opacity-30"
                            @unless ($game->status === GameStatus::RegistrationOpen) disabled @endunless>
                            Close Registration
                        </button>
                    </form>
                </div>
            </div>

            {{-- Questions --}}
            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <h3 class="font-medium text-gray-800">Trivia Questions</h3>

                @php
                    $canStart = in_array($game->status, [GameStatus::Waiting, GameStatus::QuestionClosed, GameStatus::AnswerReveal, GameStatus::Leaderboard], true);
                @endphp

                <div class="mt-3 divide-y divide-gray-100">
                    @forelse ($game->questions as $question)
                        @php
                            $isCurrent = $game->current_question_id === $question->id;
                        @endphp
                        <div class="flex items-center justify-between gap-4 py-3">
                            <div>
                                <p class="text-sm font-medium {{ $isCurrent ? 'text-brand-green' : '' }}">
                                    #{{ $question->order_number }} {{ $question->question_text }}
                                </p>
                                <p class="text-xs text-gray-500">
                                    {{ $question->duration_seconds }}s
                                    @if ($question->started_at) &middot; started {{ $question->started_at->diffForHumans() }} @endif
                                    @if ($question->closed_at) &middot; closed {{ $question->closed_at->diffForHumans() }} @endif
                                    @if ($question->revealed_at) &middot; revealed {{ $question->revealed_at->diffForHumans() }} @endif
                                </p>
                            </div>
                            <div class="flex shrink-0 gap-2">
                                <form method="POST" action="{{ route('admin.games.questions.start', [$game, $question]) }}">
                                    @csrf
                                    <button class="rounded bg-green-600 px-3 py-1.5 text-sm text-white disabled:opacity-30" @unless ($canStart) disabled @endunless>
                                        Start
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('admin.games.questions.close', [$game, $question]) }}">
                                    @csrf
                                    <button class="rounded bg-amber-600 px-3 py-1.5 text-sm text-white disabled:opacity-30"
                                        @unless ($isCurrent && $game->status === GameStatus::QuestionLive) disabled @endunless>
                                        Close
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('admin.games.questions.reveal', [$game, $question]) }}">
                                    @csrf
                                    <button class="rounded bg-blue-600 px-3 py-1.5 text-sm text-white disabled:opacity-30"
                                        @unless ($isCurrent && $game->status === GameStatus::QuestionClosed) disabled @endunless>
                                        Reveal
                                    </button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <p class="py-6 text-sm text-gray-500">No questions yet. <a href="{{ route('admin.games.questions.create', $game) }}" class="underline">Add one</a>.</p>
                    @endforelse
                </div>
            </div>

            {{-- Leaderboard / Podium --}}
            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <h3 class="font-medium text-gray-800">Leaderboard &amp; Podium</h3>
                <div class="mt-3 flex gap-3">
                    <form method="POST" action="{{ route('admin.games.leaderboard.show', $game) }}">
                        @csrf
                        <button class="rounded bg-gray-900 px-3 py-1.5 text-sm text-white disabled:opacity-30"
                            @unless (in_array($game->status, [GameStatus::QuestionClosed, GameStatus::AnswerReveal, GameStatus::Leaderboard], true)) disabled @endunless>
                            Show Leaderboard
                        </button>
                    </form>
                    <form method="POST" action="{{ route('admin.games.podium.show', $game) }}">
                        @csrf
                        <button class="rounded bg-gray-900 px-3 py-1.5 text-sm text-white disabled:opacity-30"
                            @unless (in_array($game->status, [GameStatus::Leaderboard, GameStatus::Podium], true)) disabled @endunless>
                            Show Podium
                        </button>
                    </form>
                </div>
            </div>

            {{-- Fan Predictions --}}
            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <div class="flex items-center justify-between">
                    <h3 class="font-medium text-gray-800">Fan Predictions</h3>
                    <span class="rounded-full bg-blue-100 px-3 py-1 text-xs font-medium text-brand-visa">
                        {{ $game->prediction_status ?? 'not started' }}
                    </span>
                </div>

                <div class="mt-3 flex flex-wrap gap-3">
                    <form method="POST" action="{{ route('admin.games.predictions.open', $game) }}">
                        @csrf
                        <button class="rounded bg-gray-900 px-3 py-1.5 text-sm text-white disabled:opacity-30"
                            @if (in_array($game->prediction_status, ['official_results_pending', 'prediction_results_ready'], true)) disabled @endif>
                            Open Predictions
                        </button>
                    </form>
                    <form method="POST" action="{{ route('admin.games.predictions.lock', $game) }}">
                        @csrf
                        <button class="rounded bg-amber-600 px-3 py-1.5 text-sm text-white disabled:opacity-30"
                            @unless ($game->predictionIsOpen()) disabled @endunless>
                            Lock Predictions
                        </button>
                    </form>
                    <form method="POST" action="{{ route('admin.games.predictions.dashboard', $game) }}">
                        @csrf
                        <button class="rounded bg-gray-900 px-3 py-1.5 text-sm text-white disabled:opacity-30"
                            @if (is_null($game->prediction_status)) disabled @endif>
                            Show Dashboard
                        </button>
                    </form>
                    <form method="POST" action="{{ route('admin.games.predictions.calculate', $game) }}" onsubmit="return confirm('Calculate prediction winners from the official results?');">
                        @csrf
                        <button class="rounded bg-green-600 px-3 py-1.5 text-sm text-white disabled:opacity-30" @unless ($canCalculateWinners) disabled @endunless>
                            Calculate Winners
                        </button>
                    </form>
                    <a href="{{ route('admin.games.predictions.settings', $game) }}" class="self-center text-sm underline">Match settings</a>
                    <a href="{{ route('admin.games.roster.index', $game) }}" class="self-center text-sm underline">Roster</a>
                    <a href="{{ route('admin.games.prediction-prompts.index', $game) }}" class="self-center text-sm underline">Manage prompts</a>
                </div>

                <div class="mt-4 divide-y divide-gray-100 border-t border-gray-100">
                    @forelse ($predictionPrompts as $prompt)
                        @php
                            $isCurrentPrompt = $game->current_prediction_prompt_id === $prompt->id;
                        @endphp
                        <div class="flex items-center justify-between gap-4 py-3">
                            <div>
                                <p class="text-sm font-medium {{ $prompt->is_enabled ? '' : 'text-gray-400 line-through' }} {{ $isCurrentPrompt ? 'text-brand-visa' : '' }}">
                                    {{ $prompt->prompt_label }}
                                </p>
                                <p class="text-xs text-gray-500">{{ $prompt->prompt_type }} &middot; {{ $prompt->duration_seconds ?? '—' }}s</p>
                            </div>
                            <div class="flex shrink-0 gap-2">
                                <form method="POST" action="{{ route('admin.games.prediction-prompts.start', [$game, $prompt]) }}">
                                    @csrf
                                    <button class="rounded bg-green-600 px-3 py-1.5 text-sm text-white disabled:opacity-30"
                                        @unless ($prompt->is_enabled && $game->predictionIsOpen() && ! $isCurrentPrompt) disabled @endunless>
                                        Start
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('admin.games.prediction-prompts.close', [$game, $prompt]) }}">
                                    @csrf
                                    <button class="rounded bg-amber-600 px-3 py-1.5 text-sm text-white disabled:opacity-30"
                                        @unless ($isCurrentPrompt) disabled @endunless>
                                        Close
                                    </button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <p class="py-6 text-sm text-gray-500">No prediction prompts yet. <a href="{{ route('admin.games.prediction-prompts.index', $game) }}" class="underline">Add one</a>.</p>
                    @endforelse
                </div>
            </div>

            {{-- Pause / Resume / Cancel --}}
            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <h3 class="font-medium text-gray-800">Event Controls</h3>
                <div class="mt-3 flex gap-3">
                    @if ($game->status === GameStatus::Paused)
                        <form method="POST" action="{{ route('admin.games.resume', $game) }}">
                            @csrf
                            <button class="rounded bg-green-600 px-3 py-1.5 text-sm text-white">Resume</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('admin.games.pause', $game) }}">
                            @csrf
                            <button class="rounded bg-amber-600 px-3 py-1.5 text-sm text-white disabled:opacity-30"
                                @if (in_array($game->status, [GameStatus::Cancelled, GameStatus::Podium], true)) disabled @endif>
                                Pause
                            </button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('admin.games.cancel', $game) }}" onsubmit="return confirm('Cancel this game? This cannot be undone.');">
                        @csrf
                        <button class="rounded bg-red-600 px-3 py-1.5 text-sm text-white disabled:opacity-30"
                            @if ($game->status === GameStatus::Cancelled) disabled @endif>
                            Cancel Game
                        </button>
                    </form>
                </div>
            </div>

            {{-- Recent activity --}}
            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <h3 class="font-medium text-gray-800">Recent Activity</h3>
                <ul class="mt-3 space-y-1 text-sm text-gray-600">
                    @forelse ($recentEvents as $event)
                        <li>
                            <span class="text-gray-400">{{ $event->created_at->format('H:i:s') }}</span>
                            {{ str_replace('_', ' ', $event->event_type) }}
                            @if ($event->adminUser)
                                <span class="text-gray-400">by {{ $event->adminUser->name }}</span>
                            @endif
                        </li>
                    @empty
                        <li class="text-gray-400">No activity yet.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</x-app-layout>
