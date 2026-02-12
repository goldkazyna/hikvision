<?php

namespace App\Http\Controllers;

use App\Models\Question;
use App\Models\QuizSession;
use App\Models\Reaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class QuizController extends Controller
{
    /**
     * Получить 5 случайных вопросов для новой игры
     */
    public function start()
    {
        $questions = Question::all()->shuffle()->take(5);

        \Log::info('Quiz questions: ' . $questions->pluck('id')->implode(', '));

        $questions = $questions
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

        return response()->json(['questions' => $questions])
            ->header('Cache-Control', 'no-store, no-cache');
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

ВАЖНО: На экране варианты подписаны ЛАТИНСКИМИ буквами A, B, C.
Русская "В" выглядит как латинская "B", поэтому когда пользователь говорит "В" или "вэ" или "вариант В" — он имеет в виду ВТОРОЙ вариант (b), а НЕ третий!

Соответствие:
- Первый вариант (a): "А","а","первый","один","1","вариант А"
- Второй вариант (b): "Б","б","бэ","В","в","вэ","B","второй","два","2","вариант Б","вариант В","вариант Б"
- Третий вариант (c): "С","с","си","C","третий","три","3","вариант С","вариант Ц"

Также учитывай:
- Фонетическое сходство: "ВИДИЭР"="WDR", "колор вью"="ColorVu", "эйч 265"="H.265+"
- Частичные совпадения: даже одно ключевое слово из варианта — достаточно
- Whisper искажает короткие ответы: "Б" может стать "бэм","бум","п","be"

Ответь ТОЛЬКО одной буквой: a, b или c. Если невозможно определить: none.',
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
     * Проверить код участника
     */
    public function checkCode(Request $request)
    {
        $request->validate(['audio' => 'required|file']);

        $audioFile = $request->file('audio');
        $apiKey = config('services.openai.api_key');

        // Whisper: распознаём код
        $whisperResponse = Http::withToken($apiKey)
            ->withOptions(['verify' => false])
            ->attach('file', file_get_contents($audioFile->getRealPath()), 'audio.wav')
            ->post('https://api.openai.com/v1/audio/transcriptions', [
                'model' => 'whisper-1',
                'language' => 'ru',
                'prompt' => 'Код участника: 001, 002, 003, 005, 010, 015, 020, 050',
            ]);

        if (!$whisperResponse->successful()) {
            return response()->json(['status' => 'error', 'transcript' => ''], 500);
        }

        $transcript = $whisperResponse->json('text');

        if (!$transcript) {
            return response()->json(['status' => 'not_found', 'transcript' => '']);
        }

        // Извлекаем цифры из транскрипта
        preg_match_all('/\d+/', $transcript, $matches);
        $code = null;

        if (!empty($matches[0])) {
            $num = intval(implode('', $matches[0]));
            $code = str_pad($num, 3, '0', STR_PAD_LEFT);
        }

        if (!$code) {
            return response()->json(['status' => 'not_found', 'transcript' => $transcript]);
        }

        // Ищем в БД
        $user = User::where('code', $code)->first();

        if (!$user) {
            return response()->json(['status' => 'not_found', 'transcript' => $transcript, 'code' => $code]);
        }

        if ($user->code_used) {
            return response()->json(['status' => 'used', 'transcript' => $transcript, 'code' => $code]);
        }

        // Помечаем код как использованный
        $user->code_used = true;
        $user->save();

        return response()->json([
            'status' => 'ok',
            'transcript' => $transcript,
            'code' => $code,
            'user_name' => $user->first_name,
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
            return [
                'video' => $r->video_path,
                'subtitle' => $r->subtitle_text,
            ];
        });

        return response()->json(['reactions' => $reactions]);
    }

    /**
     * Все видео вопросов (для прогрева кэша)
     */
    public function allQuestionVideos()
    {
        $videos = Question::pluck('video_question')->unique()->values();
        return response()->json(['videos' => $videos]);
    }

    /**
     * Сохранить результат викторины
     */
    public function saveResult(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
            'score' => 'required|integer|min:0|max:5',
            'answers' => 'nullable|array',
        ]);

        $user = User::where('code', $request->input('code'))->first();

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $session = QuizSession::create([
            'user_id' => $user->id,
            'score' => $request->input('score'),
            'answers' => $request->input('answers'),
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        // Отправляем результат в Telegram
        $this->sendTelegramResult($user, $request->input('score'));

        return response()->json(['ok' => true, 'session_id' => $session->id]);
    }

    private function sendTelegramResult(User $user, int $score): void
    {
        if (!$user->telegram_id) return;

        $emoji = $score === 5 ? '🏆' : ($score >= 3 ? '👏' : '💪');
        $text = "{$emoji} Результаты викторины Hikvision\n\n"
            . "Привет, {$user->first_name}!\n"
            . "Ваш результат: <b>{$score} из 5</b> правильных ответов.\n\n"
            . "Спасибо за участие!";

        Http::withOptions(['verify' => false])
            ->post('https://api.telegram.org/bot' . config('services.telegram.bot_token') . '/sendMessage', [
                'chat_id' => $user->telegram_id,
                'text' => $text,
                'parse_mode' => 'HTML',
            ]);
    }
}
