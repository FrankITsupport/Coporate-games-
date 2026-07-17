<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Answer Review — {{ $game->name }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-4xl space-y-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded bg-green-50 px-4 py-2 text-sm text-green-800">{{ session('status') }}</div>
            @endif

            <div class="divide-y divide-gray-100 bg-white shadow-sm sm:rounded-lg">
                @forelse ($pending as $answer)
                    <div class="flex items-center justify-between gap-4 p-4">
                        <div>
                            <p class="text-xs text-gray-500">{{ $answer->player->nickname }} &middot; {{ $answer->question->question_text }}</p>
                            <p class="mt-1 font-medium">"{{ $answer->answer_text }}"</p>
                            <p class="mt-1 text-xs text-gray-400">Accepted: {{ implode(', ', $answer->question->accepted_answers ?? []) }}</p>
                        </div>
                        <div class="flex shrink-0 gap-2">
                            <form method="POST" action="{{ route('admin.answers.approve', $answer) }}">
                                @csrf
                                <button class="rounded bg-green-600 px-3 py-1.5 text-sm text-white">Approve</button>
                            </form>
                            <form method="POST" action="{{ route('admin.answers.reject', $answer) }}">
                                @csrf
                                <button class="rounded bg-red-600 px-3 py-1.5 text-sm text-white">Reject</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <p class="p-6 text-sm text-gray-500">No answers waiting for review.</p>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
