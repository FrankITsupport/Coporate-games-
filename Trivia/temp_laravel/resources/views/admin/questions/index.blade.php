<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Questions — {{ $game->name }}</h2>
            <a href="{{ route('admin.games.questions.create', $game) }}" class="rounded bg-gray-900 px-3 py-1.5 text-sm text-white">Add Question</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-5xl space-y-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded bg-green-50 px-4 py-2 text-sm text-green-800">{{ session('status') }}</div>
            @endif

            <div class="divide-y divide-gray-100 bg-white shadow-sm sm:rounded-lg">
                @forelse ($questions as $question)
                    <div class="flex items-start justify-between gap-4 p-4">
                        <div>
                            <div class="flex gap-2 text-xs text-gray-500">
                                <span>#{{ $question->order_number }}</span>
                                @if ($question->round_name)
                                    <span>{{ $question->round_name }}</span>
                                @endif
                                <span>{{ $question->question_type === 'multiple_choice' ? 'Multiple choice' : 'Open text' }}</span>
                                <span>{{ $question->duration_seconds }}s</span>
                                @if ($question->is_double_points)
                                    <span class="text-amber-600">2x points</span>
                                @endif
                                @if ($question->is_sudden_death)
                                    <span class="text-red-600">Sudden death</span>
                                @endif
                            </div>
                            <p class="mt-1 font-medium">{{ $question->question_text }}</p>
                            @if ($question->question_type === 'multiple_choice')
                                <ul class="mt-1 space-y-0.5 text-sm text-gray-600">
                                    @foreach ($question->options->sortBy('order_number') as $option)
                                        <li class="{{ $option->is_enabled ? '' : 'text-gray-400 line-through' }}">
                                            {{ strtoupper($option->label) }}. {{ $option->option_text }}
                                            @if ($option->is_correct)
                                                <span class="text-green-600">(correct)</span>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                <p class="mt-1 text-sm text-gray-600">Accepted: {{ implode(', ', $question->accepted_answers ?? []) }}</p>
                                @if ($question->requires_manual_review)
                                    <p class="text-xs text-gray-500">Requires manual review</p>
                                @endif
                            @endif
                        </div>
                        <div class="flex shrink-0 flex-col items-end gap-2 text-sm">
                            <div class="flex gap-2">
                                <form method="POST" action="{{ route('admin.questions.move-up', $question) }}">
                                    @csrf
                                    <button class="underline">Up</button>
                                </form>
                                <form method="POST" action="{{ route('admin.questions.move-down', $question) }}">
                                    @csrf
                                    <button class="underline">Down</button>
                                </form>
                            </div>
                            <a href="{{ route('admin.questions.edit', $question) }}" class="underline">Edit</a>
                            <form method="POST" action="{{ route('admin.questions.duplicate', $question) }}">
                                @csrf
                                <button class="underline">Duplicate</button>
                            </form>
                            <form method="POST" action="{{ route('admin.questions.destroy', $question) }}" onsubmit="return confirm('Delete this question?');">
                                @csrf
                                @method('DELETE')
                                <button class="text-red-600 underline">Delete</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <p class="p-6 text-sm text-gray-500">No questions yet.</p>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
