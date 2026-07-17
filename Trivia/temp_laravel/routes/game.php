<?php

use App\Http\Controllers\JoinController;
use App\Http\Controllers\PlayController;
use App\Http\Controllers\PredictionController;
use App\Http\Controllers\StageController;
use Illuminate\Support\Facades\Route;

Route::get('/join/{game:code}', [JoinController::class, 'show'])->name('join.show');
Route::post('/join/{game:code}', [JoinController::class, 'store'])->name('join.store');

Route::get('/play/{game:code}', [PlayController::class, 'show'])->name('play.show');
Route::post('/play/{game:code}/questions/{question}/answer', [PlayController::class, 'submitAnswer'])->name('play.answer');

Route::get('/predict/{game:code}', [PredictionController::class, 'show'])->name('predict.show');
Route::post('/predict/{game:code}/prompts/{prompt}/answer', [PredictionController::class, 'submitAnswer'])->name('predict.answer');

Route::get('/stage/{game:code}', [StageController::class, 'show'])->name('stage.show');
