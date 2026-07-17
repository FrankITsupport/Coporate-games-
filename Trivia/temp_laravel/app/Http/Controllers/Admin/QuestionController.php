<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreQuestionRequest;
use App\Http\Requests\Admin\UpdateQuestionRequest;
use App\Models\Game;
use App\Models\Question;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class QuestionController extends Controller
{
    public function index(Game $game): View
    {
        $questions = $game->questions()->with('options')->orderBy('order_number')->get();

        return view('admin.questions.index', compact('game', 'questions'));
    }

    public function create(Game $game): View
    {
        $optionSlots = $this->blankOptionSlots();

        return view('admin.questions.create', compact('game', 'optionSlots'));
    }

    public function store(StoreQuestionRequest $request, Game $game): RedirectResponse
    {
        $data = $request->validated();

        $question = $game->questions()->create([
            'round_name' => $data['round_name'] ?? null,
            'question_text' => $data['question_text'],
            'question_type' => $data['question_type'],
            'duration_seconds' => $data['duration_seconds'],
            'order_number' => $data['order_number'] ?? (int) $game->questions()->max('order_number') + 1,
            'is_double_points' => $request->boolean('is_double_points'),
            'is_sudden_death' => $request->boolean('is_sudden_death'),
            'requires_manual_review' => $data['question_type'] === 'open_text' && $request->boolean('requires_manual_review'),
            'accepted_answers' => $data['question_type'] === 'open_text'
                ? $this->parseAcceptedAnswers($data['accepted_answers_text'] ?? '')
                : null,
        ]);

        if ($data['question_type'] === 'multiple_choice') {
            $this->syncOptions($question, $request);
        }

        return redirect()->route('admin.games.questions.index', $game)->with('status', 'Question created.');
    }

    public function edit(Question $question): View
    {
        $question->load('options');

        $optionSlots = $this->optionSlotsFor($question);

        return view('admin.questions.edit', [
            'game' => $question->game,
            'question' => $question,
            'optionSlots' => $optionSlots,
        ]);
    }

    public function update(UpdateQuestionRequest $request, Question $question): RedirectResponse
    {
        $data = $request->validated();

        $question->update([
            'round_name' => $data['round_name'] ?? null,
            'question_text' => $data['question_text'],
            'question_type' => $data['question_type'],
            'duration_seconds' => $data['duration_seconds'],
            'order_number' => $data['order_number'] ?? $question->order_number,
            'is_double_points' => $request->boolean('is_double_points'),
            'is_sudden_death' => $request->boolean('is_sudden_death'),
            'requires_manual_review' => $data['question_type'] === 'open_text' && $request->boolean('requires_manual_review'),
            'accepted_answers' => $data['question_type'] === 'open_text'
                ? $this->parseAcceptedAnswers($data['accepted_answers_text'] ?? '')
                : null,
        ]);

        if ($data['question_type'] === 'multiple_choice') {
            $this->syncOptions($question, $request);
        }

        return redirect()->route('admin.games.questions.index', $question->game)->with('status', 'Question updated.');
    }

    public function destroy(Question $question): RedirectResponse
    {
        $game = $question->game;
        $question->delete();

        return redirect()->route('admin.games.questions.index', $game)->with('status', 'Question deleted.');
    }

    public function duplicate(Question $question): RedirectResponse
    {
        $copy = $question->replicate(['started_at', 'closed_at', 'revealed_at']);
        $copy->order_number = (int) $question->game->questions()->max('order_number') + 1;
        $copy->save();

        foreach ($question->options as $option) {
            $copy->options()->create($option->only(['label', 'option_text', 'is_correct', 'is_enabled', 'order_number']));
        }

        return redirect()->route('admin.games.questions.index', $question->game)->with('status', 'Question duplicated.');
    }

    public function moveUp(Question $question): RedirectResponse
    {
        $previous = $question->game->questions()
            ->where('order_number', '<', $question->order_number)
            ->orderByDesc('order_number')
            ->first();

        $this->swapOrder($question, $previous);

        return redirect()->route('admin.games.questions.index', $question->game);
    }

    public function moveDown(Question $question): RedirectResponse
    {
        $next = $question->game->questions()
            ->where('order_number', '>', $question->order_number)
            ->orderBy('order_number')
            ->first();

        $this->swapOrder($question, $next);

        return redirect()->route('admin.games.questions.index', $question->game);
    }

    private function swapOrder(?Question $a, ?Question $b): void
    {
        if (! $a || ! $b) {
            return;
        }

        [$orderA, $orderB] = [$a->order_number, $b->order_number];
        $a->update(['order_number' => $orderB]);
        $b->update(['order_number' => $orderA]);
    }

    private function blankOptionSlots(): \Illuminate\Support\Collection
    {
        return collect(range(0, 3))->map(fn () => [
            'id' => null,
            'option_text' => '',
            'is_enabled' => true,
            'is_correct' => false,
        ]);
    }

    private function optionSlotsFor(Question $question): \Illuminate\Support\Collection
    {
        $existing = $question->options->sortBy('order_number')->values();

        return collect(range(0, 3))->map(function (int $i) use ($existing) {
            $option = $existing->get($i);

            return [
                'id' => $option->id ?? null,
                'option_text' => $option->option_text ?? '',
                'is_enabled' => $option->is_enabled ?? true,
                'is_correct' => $option->is_correct ?? false,
            ];
        });
    }

    private function parseAcceptedAnswers(string $text): array
    {
        return collect(preg_split('/\r\n|\r|\n/', $text))
            ->map(fn ($line) => trim($line))
            ->filter()
            ->values()
            ->all();
    }

    private function syncOptions(Question $question, StoreQuestionRequest|UpdateQuestionRequest $request): void
    {
        $options = $request->input('options', []);
        $correctIndex = (int) $request->input('correct_option');
        $order = 0;

        foreach ($options as $index => $option) {
            $text = trim($option['option_text'] ?? '');
            $id = $option['id'] ?? null;

            if ($text === '') {
                continue;
            }

            $attributes = [
                'label' => chr(97 + $order),
                'option_text' => $text,
                'is_correct' => (int) $index === $correctIndex,
                'is_enabled' => isset($option['is_enabled']),
                'order_number' => $order,
            ];

            if ($id) {
                $question->options()->whereKey($id)->update($attributes);
            } else {
                $question->options()->create($attributes);
            }

            $order++;
        }
    }
}
