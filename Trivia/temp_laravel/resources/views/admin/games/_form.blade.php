@csrf
@if (isset($game))
    @method('PATCH')
@endif

<div>
    <x-input-label for="name" value="Game name" />
    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" value="{{ old('name', $game->name ?? '') }}" required autofocus />
    <x-input-error :messages="$errors->get('name')" class="mt-2" />
</div>

<div class="mt-4">
    <x-input-label for="code" value="Join code" />
    <x-text-input id="code" name="code" type="text" class="mt-1 block w-full uppercase" value="{{ old('code', $game->code ?? $suggestedCode ?? '') }}" required />
    <p class="mt-1 text-sm text-gray-500">Players join at /join/{code}.</p>
    <x-input-error :messages="$errors->get('code')" class="mt-2" />
</div>

<div class="mt-4">
    <x-input-label value="Game type" />
    <div class="mt-2 space-y-1">
        @foreach (['trivia' => 'Trivia only', 'predictions' => 'Fan Predictions only', 'both' => 'Trivia + Fan Predictions'] as $value => $label)
            <label class="flex items-center gap-2 text-sm">
                <input type="radio" name="event_type" value="{{ $value }}" {{ old('event_type', $game->event_type ?? 'both') === $value ? 'checked' : '' }}>
                {{ $label }}
            </label>
        @endforeach
    </div>
    <x-input-error :messages="$errors->get('event_type')" class="mt-2" />
</div>

<div class="mt-6 flex items-center gap-4">
    <x-primary-button>{{ isset($game) ? 'Save Game' : 'Create Game' }}</x-primary-button>
    <a href="{{ isset($game) ? route('admin.games.show', $game) : route('admin.games.index') }}" class="text-sm text-gray-600 underline">Cancel</a>
</div>
