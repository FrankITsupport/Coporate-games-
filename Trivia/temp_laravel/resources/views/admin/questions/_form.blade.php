@csrf
@if (isset($question))
    @method('PATCH')
@endif

<div x-data="{ type: '{{ old('question_type', $question->question_type ?? 'multiple_choice') }}' }">
    <div>
        <x-input-label for="round_name" value="Round name" />
        <x-text-input id="round_name" name="round_name" type="text" class="mt-1 block w-full" value="{{ old('round_name', $question->round_name ?? '') }}" />
        <x-input-error :messages="$errors->get('round_name')" class="mt-2" />
    </div>

    <div class="mt-4">
        <x-input-label for="question_text" value="Question text" />
        <textarea id="question_text" name="question_text" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>{{ old('question_text', $question->question_text ?? '') }}</textarea>
        <x-input-error :messages="$errors->get('question_text')" class="mt-2" />
    </div>

    <div class="mt-4 grid grid-cols-2 gap-4">
        <div>
            <x-input-label for="duration_seconds" value="Duration (seconds)" />
            <x-text-input id="duration_seconds" name="duration_seconds" type="number" min="5" max="600" class="mt-1 block w-full" value="{{ old('duration_seconds', $question->duration_seconds ?? 20) }}" required />
            <x-input-error :messages="$errors->get('duration_seconds')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="order_number" value="Order number" />
            <x-text-input id="order_number" name="order_number" type="number" min="0" class="mt-1 block w-full" value="{{ old('order_number', $question->order_number ?? '') }}" />
            <x-input-error :messages="$errors->get('order_number')" class="mt-2" />
        </div>
    </div>

    <div class="mt-4 flex gap-6">
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="is_double_points" value="1" {{ old('is_double_points', $question->is_double_points ?? false) ? 'checked' : '' }}>
            Double points
        </label>
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="is_sudden_death" value="1" {{ old('is_sudden_death', $question->is_sudden_death ?? false) ? 'checked' : '' }}>
            Sudden death
        </label>
    </div>

    <div class="mt-6">
        <x-input-label value="Question type" />
        <div class="mt-2 flex gap-6">
            <label class="flex items-center gap-2 text-sm">
                <input type="radio" name="question_type" value="multiple_choice" x-model="type">
                Multiple choice
            </label>
            <label class="flex items-center gap-2 text-sm">
                <input type="radio" name="question_type" value="open_text" x-model="type">
                Open text
            </label>
        </div>
        <x-input-error :messages="$errors->get('question_type')" class="mt-2" />
    </div>

    <div x-show="type === 'multiple_choice'" class="mt-6 space-y-3">
        <x-input-label value="Answer options (up to 4, at least 2 required)" />
        @foreach ($optionSlots as $index => $slot)
            <div class="flex items-center gap-3">
                <input type="radio" name="correct_option" value="{{ $index }}" title="Correct answer" {{ (int) old('correct_option', $slot['is_correct'] ? $index : -1) === $index ? 'checked' : '' }}>
                <input type="hidden" name="options[{{ $index }}][id]" value="{{ $slot['id'] }}">
                <input type="text" name="options[{{ $index }}][option_text]" value="{{ old("options.$index.option_text", $slot['option_text']) }}" placeholder="Option {{ $index + 1 }}" class="block flex-1 rounded-md border-gray-300 text-sm shadow-sm">
                <label class="flex items-center gap-1 text-xs text-gray-500">
                    <input type="checkbox" name="options[{{ $index }}][is_enabled]" value="1" {{ old("options.$index.is_enabled", $slot['is_enabled']) ? 'checked' : '' }}>
                    Enabled
                </label>
            </div>
        @endforeach
        <x-input-error :messages="$errors->get('options')" class="mt-2" />
        <x-input-error :messages="$errors->get('correct_option')" class="mt-2" />
    </div>

    <div x-show="type === 'open_text'" class="mt-6 space-y-3">
        <div>
            <x-input-label for="accepted_answers_text" value="Accepted answers (one per line)" />
            <textarea id="accepted_answers_text" name="accepted_answers_text" rows="4" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">{{ old('accepted_answers_text', isset($question) ? implode("\n", $question->accepted_answers ?? []) : '') }}</textarea>
            <p class="mt-1 text-sm text-gray-500">Matching is case-insensitive and trims extra spaces.</p>
            <x-input-error :messages="$errors->get('accepted_answers_text')" class="mt-2" />
        </div>
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="requires_manual_review" value="1" {{ old('requires_manual_review', $question->requires_manual_review ?? false) ? 'checked' : '' }}>
            Requires manual review (admin approves/rejects before scoring)
        </label>
    </div>

    <div class="mt-6 flex items-center gap-4">
        <x-primary-button>{{ isset($question) ? 'Save Question' : 'Add Question' }}</x-primary-button>
        <a href="{{ route('admin.games.questions.index', $game) }}" class="text-sm text-gray-600 underline">Cancel</a>
    </div>
</div>
