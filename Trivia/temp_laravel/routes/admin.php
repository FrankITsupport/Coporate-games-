<?php

use App\Http\Controllers\Admin\AnswerReviewController;
use App\Http\Controllers\Admin\GameControlController;
use App\Http\Controllers\Admin\GameController;
use App\Http\Controllers\Admin\PlayerController;
use App\Http\Controllers\Admin\PredictionControlController;
use App\Http\Controllers\Admin\PredictionPlayerController;
use App\Http\Controllers\Admin\PredictionPromptController;
use App\Http\Controllers\Admin\PredictionSettingController;
use App\Http\Controllers\Admin\QuestionController;
use App\Http\Controllers\Admin\ResultsController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('admin')->name('admin.')->group(function () {
    Route::redirect('/', '/admin/games')->name('home');

    Route::get('games', [GameController::class, 'index'])->name('games.index');
    Route::get('games/create', [GameController::class, 'create'])->name('games.create');
    Route::post('games', [GameController::class, 'store'])->name('games.store');
    Route::get('games/{game}', [GameController::class, 'show'])->name('games.show');
    Route::get('games/{game}/edit', [GameController::class, 'edit'])->name('games.edit');
    Route::patch('games/{game}', [GameController::class, 'update'])->name('games.update');

    Route::get('games/{game}/control', [GameControlController::class, 'index'])->name('games.control');
    Route::post('games/{game}/registration/open', [GameControlController::class, 'openRegistration'])->name('games.registration.open');
    Route::post('games/{game}/registration/close', [GameControlController::class, 'closeRegistration'])->name('games.registration.close');
    Route::post('games/{game}/questions/{question}/start', [GameControlController::class, 'startQuestion'])->name('games.questions.start');
    Route::post('games/{game}/questions/{question}/close', [GameControlController::class, 'closeQuestion'])->name('games.questions.close');
    Route::post('games/{game}/questions/{question}/reveal', [GameControlController::class, 'revealQuestion'])->name('games.questions.reveal');
    Route::post('games/{game}/leaderboard/show', [GameControlController::class, 'showLeaderboard'])->name('games.leaderboard.show');
    Route::post('games/{game}/podium/show', [GameControlController::class, 'showPodium'])->name('games.podium.show');
    Route::post('games/{game}/pause', [GameControlController::class, 'pause'])->name('games.pause');
    Route::post('games/{game}/resume', [GameControlController::class, 'resume'])->name('games.resume');
    Route::post('games/{game}/cancel', [GameControlController::class, 'cancel'])->name('games.cancel');

    Route::get('games/{game}/questions', [QuestionController::class, 'index'])->name('games.questions.index');
    Route::get('games/{game}/questions/create', [QuestionController::class, 'create'])->name('games.questions.create');
    Route::post('games/{game}/questions', [QuestionController::class, 'store'])->name('games.questions.store');

    Route::get('questions/{question}/edit', [QuestionController::class, 'edit'])->name('questions.edit');
    Route::patch('questions/{question}', [QuestionController::class, 'update'])->name('questions.update');
    Route::delete('questions/{question}', [QuestionController::class, 'destroy'])->name('questions.destroy');
    Route::post('questions/{question}/duplicate', [QuestionController::class, 'duplicate'])->name('questions.duplicate');
    Route::post('questions/{question}/move-up', [QuestionController::class, 'moveUp'])->name('questions.move-up');
    Route::post('questions/{question}/move-down', [QuestionController::class, 'moveDown'])->name('questions.move-down');

    Route::get('games/{game}/players', [PlayerController::class, 'index'])->name('games.players.index');
    Route::patch('games/{game}/players/{player}/nickname', [PlayerController::class, 'updateNickname'])->name('games.players.nickname');
    Route::post('games/{game}/players/{player}/disqualify', [PlayerController::class, 'disqualify'])->name('games.players.disqualify');
    Route::post('games/{game}/players/{player}/reinstate', [PlayerController::class, 'reinstate'])->name('games.players.reinstate');

    Route::get('games/{game}/review', [AnswerReviewController::class, 'index'])->name('games.review.index');
    Route::post('answers/{answer}/approve', [AnswerReviewController::class, 'approve'])->name('answers.approve');
    Route::post('answers/{answer}/reject', [AnswerReviewController::class, 'reject'])->name('answers.reject');

    Route::get('games/{game}/results', [ResultsController::class, 'show'])->name('games.results.show');
    Route::get('games/{game}/results/export/trivia', [ResultsController::class, 'exportTrivia'])->name('games.results.export.trivia');
    Route::get('games/{game}/results/export/predictions', [ResultsController::class, 'exportPredictions'])->name('games.results.export.predictions');

    // ─── Fan Prediction Setup (Phase 3B) ───
    Route::get('games/{game}/roster', [PredictionPlayerController::class, 'index'])->name('games.roster.index');
    Route::post('games/{game}/roster', [PredictionPlayerController::class, 'store'])->name('games.roster.store');
    Route::patch('games/{game}/roster/{predictionPlayer}', [PredictionPlayerController::class, 'update'])->name('games.roster.update');
    Route::delete('games/{game}/roster/{predictionPlayer}', [PredictionPlayerController::class, 'destroy'])->name('games.roster.destroy');
    Route::post('games/{game}/roster/seed', [PredictionPlayerController::class, 'seed'])->name('games.roster.seed');

    Route::get('games/{game}/predictions/settings', [PredictionSettingController::class, 'edit'])->name('games.predictions.settings');
    Route::post('games/{game}/predictions/settings', [PredictionSettingController::class, 'update'])->name('games.predictions.settings.update');
    Route::post('games/{game}/predictions/results', [PredictionSettingController::class, 'storeResults'])->name('games.predictions.results');

    Route::get('games/{game}/prediction-prompts', [PredictionPromptController::class, 'index'])->name('games.prediction-prompts.index');
    Route::post('games/{game}/prediction-prompts', [PredictionPromptController::class, 'store'])->name('games.prediction-prompts.store');
    Route::patch('games/{game}/prediction-prompts/{predictionPrompt}', [PredictionPromptController::class, 'update'])->name('games.prediction-prompts.update');
    Route::delete('games/{game}/prediction-prompts/{predictionPrompt}', [PredictionPromptController::class, 'destroy'])->name('games.prediction-prompts.destroy');
    Route::post('games/{game}/prediction-prompts/{predictionPrompt}/move-up', [PredictionPromptController::class, 'moveUp'])->name('games.prediction-prompts.move-up');
    Route::post('games/{game}/prediction-prompts/{predictionPrompt}/move-down', [PredictionPromptController::class, 'moveDown'])->name('games.prediction-prompts.move-down');

    // ─── Fan Prediction live controls (Phase 5) ───
    Route::post('games/{game}/predictions/open', [PredictionControlController::class, 'openPredictions'])->name('games.predictions.open');
    Route::post('games/{game}/prediction-prompts/{predictionPrompt}/start', [PredictionControlController::class, 'startPrompt'])->name('games.prediction-prompts.start');
    Route::post('games/{game}/prediction-prompts/{predictionPrompt}/close', [PredictionControlController::class, 'closePrompt'])->name('games.prediction-prompts.close');
    Route::post('games/{game}/predictions/lock', [PredictionControlController::class, 'lockPredictions'])->name('games.predictions.lock');
    Route::post('games/{game}/predictions/dashboard', [PredictionControlController::class, 'showDashboard'])->name('games.predictions.dashboard');
    Route::post('games/{game}/predictions/calculate', [PredictionControlController::class, 'calculateWinners'])->name('games.predictions.calculate');
});
