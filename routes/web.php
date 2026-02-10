<?php

use App\Http\Controllers\QuizController;
use App\Http\Controllers\TelegramController;
use App\Http\Controllers\WhisperController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/whisper', [WhisperController::class, 'transcribe']);
Route::get('/quiz/start', [QuizController::class, 'start']);
Route::get('/quiz/reaction/{type}', [QuizController::class, 'reaction']);
Route::get('/quiz/reactions/all', [QuizController::class, 'allReactions']);
Route::post('/quiz/check-answer', [QuizController::class, 'checkAnswer']);
Route::post('/telegram/webhook', [TelegramController::class, 'handle']);
