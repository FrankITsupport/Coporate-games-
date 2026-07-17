<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Games</h2>
            <a href="{{ route('admin.games.create') }}" class="rounded bg-brand-green px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-lime">New Game</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-5xl space-y-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded bg-green-50 px-4 py-2 text-sm text-green-800">{{ session('status') }}</div>
            @endif

            <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left">Name</th>
                            <th class="px-4 py-2 text-left">Code</th>
                            <th class="px-4 py-2 text-left">Type</th>
                            <th class="px-4 py-2 text-left">Status</th>
                            <th class="px-4 py-2 text-left">Questions</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($games as $game)
                            <tr>
                                <td class="px-4 py-2"><a href="{{ route('admin.games.show', $game) }}" class="underline">{{ $game->name }}</a></td>
                                <td class="px-4 py-2 font-mono">{{ $game->code }}</td>
                                <td class="px-4 py-2">{{ $game->event_type }}</td>
                                <td class="px-4 py-2">{{ $game->status->value }}</td>
                                <td class="px-4 py-2">{{ $game->questions_count }}</td>
                                <td class="px-4 py-2 text-right"><a href="{{ route('admin.games.edit', $game) }}" class="underline">Edit</a></td>
                            </tr>
                        @empty
                            <tr>
                                <td class="px-4 py-6 text-gray-500" colspan="6">No games yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
