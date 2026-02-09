<?php

namespace App\Http\Controllers;

use App\Models\Question;
use App\Models\Reaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class QuizController extends Controller
{
    /**
     * Получить 5 случайных вопросов для новой игры
     */
    public function start()
    {
        $questions = Question::inRandomOrder()
            ->limit(5)
            ->get()
            ->map(function ($q) {
                return [
                    'id' => $q->id,
                    'video' => $q->video_question,
                    'subtitle' => $q->subtitle_text,
                    'options' => [
                        'a' => $q->option_a,
                        'b' => $q->option_b,
                        'c' => $q->option_c,
                    ],
                    'correct' => strtolower($q->correct_answer),
                ];
            });

        return response()->json(['questions' => $questions]);
    }

    /**
     * Проверить голосовой ответ через Whisper + GPT
     */
    public function checkAnswer(Request $request)
    {
        $request->validate([
            'audio' => 'required|file',
            'option_a' => 'required|string',
            'option_b' => 'required|string',
            'option_c' => 'required|string',
        ]);

        $audioFile = $request->file('audio');
        $apiKey = config('services.openai.api_key');

        // 1. Whisper: аудио → текст (с подсказкой ожидаемых слов)
        $promptHint = $request->input('prompt_hint', '');
        $whisperParams = [
            'model' => 'whisper-1',
            'language' => 'ru',
        ];
        if ($promptHint) {
            $whisperParams['prompt'] = $promptHint;
        }

        $whisperResponse = Http::withToken($apiKey)
            ->withOptions(['verify' => false])
            ->attach('file', file_get_contents($audioFile->getRealPath()), 'audio.wav')
            ->post('https://api.openai.com/v1/audio/transcriptions', $whisperParams);

        if (!$whisperResponse->successful()) {
            return response()->json(['error' => 'Whisper error', 'answer' => null], 500);
        }

        $transcript = $whisperResponse->json('text');

        if (!$transcript) {
            return response()->json(['answer' => null, 'transcript' => '']);
        }

        // 2. GPT: определяем какой вариант выбрал пользователь
        $optionA = $request->input('option_a');
        $optionB = $request->input('option_b');
        $optionC = $request->input('option_c');

        $gptResponse = Http::withToken($apiKey)
            ->withOptions(['verify' => false])
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4o-mini',
                'temperature' => 0,
                'max_tokens' => 10,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Ты определяешь, какой вариант ответа выбрал пользователь в голосовой викторине.

Варианты: A) '.$optionA.', B) '.$optionB.', C) '.$optionC.'

Речь была распознана автоматически и может содержать ошибки. Учитывай:
- Буквы и номера: "А","Б","В","A","B","C","первый","второй","третий","вариант А" и т.д.
- Фонетическое сходство: английские термины могут быть произнесены на русский лад или распознаны с ошибками (например "ВИДИЭР"="WDR", "колор вью"="ColorVu", "эйч 265"="H.265+")
- Частичные совпадения: даже одно ключевое слово из варианта — достаточно для определения

Будь максимально гибким! Ответь ТОЛЬКО одной буквой: a, b или c. Если совсем невозможно определить: none.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $transcript,
                    ],
                ],
            ]);

        if (!$gptResponse->successful()) {
            return response()->json(['error' => 'GPT error', 'answer' => null, 'transcript' => $transcript], 500);
        }

        $gptAnswer = trim(strtolower($gptResponse->json('choices.0.message.content', 'none')));

        // Нормализуем ответ
        if (!in_array($gptAnswer, ['a', 'b', 'c'])) {
            $gptAnswer = null;
        }

        return response()->json([
            'answer' => $gptAnswer,
            'transcript' => $transcript,
        ]);
    }

    /**
     * Получить случайную реакцию по типу (correct/wrong)
     */
    public function reaction(string $type)
    {
        $reaction = Reaction::where('type', $type)
            ->inRandomOrder()
            ->first();

        if (!$reaction) {
            return response()->json(['error' => 'No reaction found'], 404);
        }

        return response()->json([
            'video' => $reaction->video_path,
            'subtitle' => $reaction->subtitle_text,
        ]);
    }

    /**
     * Все реакции (для preload видео)
     */
    public function allReactions()
    {
        $reactions = Reaction::all()->map(function ($r) {
            return $r->video_path;
        });

        return response()->json(['videos' => $reactions]);
    }
}
