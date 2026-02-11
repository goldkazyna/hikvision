<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class TelegramController extends Controller
{
    private function sendMessage(int $chatId, string $text, array $replyMarkup = null): void
    {
        $params = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ];

        if ($replyMarkup) {
            $params['reply_markup'] = json_encode($replyMarkup);
        }

        Http::withOptions(['verify' => false])
            ->post('https://api.telegram.org/bot' . config('services.telegram.bot_token') . '/sendMessage', $params);
    }

    public function handle(Request $request)
    {
        $update = $request->all();
        $message = $update['message'] ?? null;

        if (!$message) {
            return response()->json(['ok' => true]);
        }

        $chatId = $message['chat']['id'];
        $telegramId = $message['from']['id'];

        // Команда /start
        if (isset($message['text']) && str_starts_with($message['text'], '/start')) {
            $this->handleStart($chatId, $telegramId);
            return response()->json(['ok' => true]);
        }

        // Поделились контактом (номером телефона)
        if (isset($message['contact'])) {
            $this->handleContact($chatId, $telegramId, $message['contact']);
            return response()->json(['ok' => true]);
        }

        // Текстовое сообщение — может быть ФИО или email
        if (isset($message['text'])) {
            $this->handleText($chatId, $telegramId, $message['text']);
            return response()->json(['ok' => true]);
        }

        return response()->json(['ok' => true]);
    }

    private function handleStart(int $chatId, int $telegramId): void
    {
        $user = User::where('telegram_id', $telegramId)->first();

        // Уже полностью зарегистрирован
        if ($user && $user->code) {
            $this->sendMessage($chatId,
                "Вы уже зарегистрированы!\n\nВаш код участника: <b>{$user->code}</b>\n\nНазовите этот код на стенде для начала викторины."
            );
            return;
        }

        // Есть незавершённая регистрация — удаляем и начинаем заново
        if ($user) {
            $user->delete();
        }

        $this->sendMessage($chatId,
            "Привет! Я бот викторины Hikvision.\n\nПоделитесь номером телефона для регистрации.",
            [
                'keyboard' => [
                    [
                        ['text' => '📱 Поделиться номером', 'request_contact' => true],
                    ],
                ],
                'resize_keyboard' => true,
                'one_time_keyboard' => true,
            ]
        );
    }

    private function handleContact(int $chatId, int $telegramId, array $contact): void
    {
        $user = User::where('telegram_id', $telegramId)->first();

        // Уже полностью зарегистрирован
        if ($user && $user->code) {
            $this->sendMessage($chatId,
                "Вы уже зарегистрированы!\n\nВаш код участника: <b>{$user->code}</b>"
            );
            return;
        }

        // Удаляем старую незавершённую запись если есть
        if ($user) {
            $user->delete();
        }

        // Создаём пользователя с телефоном, без ФИО и email
        User::create([
            'telegram_id' => $telegramId,
            'phone' => $contact['phone_number'],
            'first_name' => '',
            'last_name' => '',
            'email' => '',
            'code' => '',
        ]);

        $this->sendMessage($chatId,
            "Отлично! Теперь введите ваше <b>ФИО</b> (минимум 3 символа):",
            ['remove_keyboard' => true]
        );
    }

    private function handleText(int $chatId, int $telegramId, string $text): void
    {
        $user = User::where('telegram_id', $telegramId)->first();

        if (!$user) {
            $this->sendMessage($chatId, "Нажмите /start для начала регистрации.");
            return;
        }

        // Уже полностью зарегистрирован
        if ($user->code) {
            $this->sendMessage($chatId,
                "Вы уже зарегистрированы!\n\nВаш код участника: <b>{$user->code}</b>\n\nНазовите этот код на стенде для начала викторины."
            );
            return;
        }

        // Шаг 1: ждём ФИО (first_name пустой)
        if ($user->first_name === '') {
            $text = trim($text);

            if (mb_strlen($text) < 3) {
                $this->sendMessage($chatId, "ФИО слишком короткое. Введите минимум 3 символа:");
                return;
            }

            // Разбиваем на имя и фамилию
            $parts = preg_split('/\s+/', $text, 2);
            $user->first_name = $parts[0];
            $user->last_name = $parts[1] ?? '';
            $user->save();

            $this->sendMessage($chatId, "Спасибо! Теперь введите ваш <b>email</b>:");
            return;
        }

        // Шаг 2: ждём email (email пустой)
        if ($user->email === '') {
            $text = trim($text);

            if (!filter_var($text, FILTER_VALIDATE_EMAIL)) {
                $this->sendMessage($chatId, "Некорректный email. Попробуйте ещё раз:");
                return;
            }

            $user->email = $text;
            $user->code = strval($user->id + 99);
            $user->save();

            $this->sendMessage($chatId,
                "Регистрация завершена! 🎉\n\nВаш код участника: <b>{$user->code}</b>\n\nНазовите этот код на стенде для начала викторины."
            );
        }
    }
}
