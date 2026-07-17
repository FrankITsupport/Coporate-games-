<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Prediction Prompts — {{ $game->name }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-4xl space-y-6 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded bg-green-50 px-4 py-2 text-sm text-green-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded bg-red-50 px-4 py-2 text-sm text-red-800">{{ $errors->first() }}</div>
            @endif

            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <h3 class="font-medium text-gray-800">Prompts</h3>
                <div class="mt-3 divide-y divide-gray-100">
                    @forelse ($prompts as $prompt)
                        <div class="flex items-center justify-between gap-4 py-3">
                            <div>
                                <p class="text-sm font-medium {{ $prompt->is_enabled ? '' : 'text-gray-400 line-through' }}">
                                    {{ $prompt->prompt_label }}
                                </p>
                                <p class="text-xs text-gray-500">{{ $prompt->prompt_key }} &middot; {{ $prompt->prompt_type }} &middot; {{ $prompt->duration_seconds ?? '—' }}s</p>
                            </div>
                            <div class="flex shrink-0 items-center gap-2">
                                <form method="POST" action="{{ route('admin.games.prediction-prompts.move-up', [$game, $prompt]) }}">
                                    @csrf
                                    <button class="rounded bg-gray-200 px-2 py-1 text-xs">Up</button>
                                </form>
                                <form method="POST" action="{{ route('admin.games.prediction-prompts.move-down', [$game, $prompt]) }}">
                                    @csrf
                                    <button class="rounded bg-gray-200 px-2 py-1 text-xs">Down</button>
                                </form>
                                <form method="POST" action="{{ route('admin.games.prediction-prompts.update', [$game, $prompt]) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="prompt_key" value="{{ $prompt->prompt_key }}">
                                    <input type="hidden" name="prompt_label" value="{{ $prompt->prompt_label }}">
                                    <input type="hidden" name="prompt_type" value="{{ $prompt->prompt_type }}">
                                    <input type="hidden" name="duration_seconds" value="{{ $prompt->duration_seconds }}">
                                    <input type="hidden" name="is_enabled" value="{{ $prompt->is_enabled ? 0 : 1 }}">
                                    <button class="rounded {{ $prompt->is_enabled ? 'bg-amber-600' : 'bg-green-600' }} px-2 py-1 text-xs text-white">
                                        {{ $prompt->is_enabled ? 'Disable' : 'Enable' }}
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('admin.games.prediction-prompts.destroy', [$game, $prompt]) }}" onsubmit="return confirm('Delete this prompt?');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="rounded bg-red-600 px-2 py-1 text-xs text-white">Delete</button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <p class="py-6 text-sm text-gray-500">No prediction prompts yet.</p>
                    @endforelse
                </div>
            </div>

            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <h3 class="font-medium text-gray-800">Add Prompt</h3>
                <form method="POST" action="{{ route('admin.games.prediction-prompts.store', $game) }}" class="mt-4 space-y-4">
                    @csrf
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="prompt_key" value="Prompt key" />
                            <select id="prompt_key" name="prompt_key" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm" required>
                                @foreach ($availableKeys as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="prompt_type" value="Prompt type" />
                            <select id="prompt_type" name="prompt_type" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm" required>
                                @foreach ($availableTypes as $type => $label)
                                    <option value="{{ $type }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="prompt_label" value="Player-facing label" />
                            <x-text-input id="prompt_label" name="prompt_label" type="text" class="mt-1 block w-full" placeholder="Who will win?" required />
                        </div>
                        <div>
                            <x-input-label for="duration_seconds" value="Duration (seconds, optional)" />
                            <x-text-input id="duration_seconds" name="duration_seconds" type="number" min="5" class="mt-1 block w-full" />
                        </div>
                    </div>
                    <x-primary-button>Add Prompt</x-primary-button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
