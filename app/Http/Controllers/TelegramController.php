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
            $this->handleStart($chatId, $telegramId, $message['from']);
            return response()->json(['ok' => true]);
        }

        // Поделились контактом (номером телефона)
        if (isset($message['contact'])) {
            $this->handleContact($chatId, $telegramId, $message['from'], $message['contact']);
            return response()->json(['ok' => true]);
        }

        return response()->json(['ok' => true]);
    }

    private function handleStart(int $chatId, int $telegramId, array $from): void
    {
        // Проверяем — может уже зарегистрирован
        $user = User::where('telegram_id', $telegramId)->first();

        if ($user) {
            $this->sendMessage($chatId,
                "Вы уже зарегистрированы!\n\nВаш код участника: <b>{$user->code}</b>\n\nНазовите этот код на стенде для начала викторины."
            );
            return;
        }

        $this->sendMessage($chatId,
            "Привет! Я бот викторины Hikvision.\n\nПоделитесь номером телефона для получения кода участника.",
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

    private function handleContact(int $chatId, int $telegramId, array $from, array $contact): void
    {
        // Проверяем — может уже зарегистрирован
        $user = User::where('telegram_id', $telegramId)->first();

        if ($user) {
            $this->sendMessage($chatId,
                "Вы уже зарегистрированы!\n\nВаш код участника: <b>{$user->code}</b>"
            );
            return;
        }

        $phone = $contact['phone_number'];
        $firstName = $contact['first_name'] ?? $from['first_name'] ?? '';
        $lastName = $contact['last_name'] ?? $from['last_name'] ?? '';

        // Создаём пользователя
        $user = User::create([
            'telegram_id' => $telegramId,
            'phone' => $phone,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => '',
            'code' => '', // временно
        ]);

        // Код = порядковый номер (id)
        $user->code = str_pad($user->id, 3, '0', STR_PAD_LEFT);
        $user->save();

        // Убираем клавиатуру и показываем код
        $this->sendMessage($chatId,
            "Спасибо, {$firstName}!\n\nВаш код участника: <b>{$user->code}</b>\n\nНазовите этот код на стенде для начала викторины.",
            ['remove_keyboard' => true]
        );
    }
}
