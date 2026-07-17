<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

abstract class QuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'round_name' => ['nullable', 'string', 'max:255'],
            'question_text' => ['required', 'string'],
            'question_type' => ['required', Rule::in(['multiple_choice', 'open_text'])],
            'duration_seconds' => ['required', 'integer', 'min:5', 'max:600'],
            'order_number' => ['nullable', 'integer', 'min:0'],
            'is_double_points' => ['nullable', 'boolean'],
            'is_sudden_death' => ['nullable', 'boolean'],
            'requires_manual_review' => ['nullable', 'boolean'],
            'accepted_answers_text' => ['nullable', 'string'],
            'correct_option' => ['nullable', 'integer'],
            'options' => ['nullable', 'array'],
            'options.*.id' => ['nullable', 'integer'],
            'options.*.option_text' => ['nullable', 'string', 'max:255'],
            'options.*.is_enabled' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->input('question_type') === 'multiple_choice') {
                $filledOptions = collect($this->input('options', []))
                    ->filter(fn ($option) => filled($option['option_text'] ?? null));

                if ($filledOptions->count() < 2) {
                    $validator->errors()->add('options', 'Provide at least two answer options.');
                }

                $correctIndex = $this->input('correct_option');
                $correctText = $correctIndex !== null
                    ? $this->input("options.{$correctIndex}.option_text")
                    : null;

                if (blank($correctText)) {
                    $validator->errors()->add('correct_option', 'Mark one option as correct.');
                }
            }

            if ($this->input('question_type') === 'open_text' && blank($this->input('accepted_answers_text'))) {
                $validator->errors()->add('accepted_answers_text', 'Provide at least one accepted answer.');
            }
        });
    }
}
