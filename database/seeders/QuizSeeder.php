<?php

namespace Database\Seeders;

use App\Models\Question;
use App\Models\Reaction;
use Illuminate\Database\Seeder;

class QuizSeeder extends Seeder
{
    public function run(): void
    {
        // Вопросы
        $questions = [
            [
                'question_text' => 'Какая технология Hikvision обеспечивает цветное изображение ночью?',
                'subtitle_text' => 'Какая технология <span class="hl">Hikvision</span> обеспечивает цветное изображение ночью?',
                'video_question' => '/videos/q1.mp4',
                'option_a' => 'ColorVu',
                'option_b' => 'DarkFighter',
                'option_c' => 'WDR',
                'correct_answer' => 'A',
            ],
            [
                'question_text' => 'Какой максимальный объём хранилища поддерживает NVR серии I?',
                'subtitle_text' => 'Какой максимальный объём хранилища поддерживает NVR серии I?',
                'video_question' => '/videos/q2.mp4',
                'option_a' => '16 ТБ',
                'option_b' => '40 ТБ',
                'option_c' => '100 ТБ',
                'correct_answer' => 'B',
            ],
            [
                'question_text' => 'Для чего используется интерфейс RS-485 в системах Hikvision?',
                'subtitle_text' => 'Для чего используется интерфейс <span class="hl">RS-485</span> в системах Hikvision?',
                'video_question' => '/videos/q3.mp4',
                'option_a' => 'Для подключения камер',
                'option_b' => 'Для подключения считывателей и модулей расширения',
                'option_c' => 'Для интернета',
                'correct_answer' => 'B',
            ],
            [
                'question_text' => 'Что означает степень защиты IP67 у камер Hikvision?',
                'subtitle_text' => 'Что означает степень защиты <span class="hl">IP67</span> у камер Hikvision?',
                'video_question' => '/videos/q4.mp4',
                'option_a' => 'Защита от пыли',
                'option_b' => 'Защита от взлома',
                'option_c' => 'Полная пылезащита и кратковременное погружение в воду',
                'correct_answer' => 'C',
            ],
            [
                'question_text' => 'Какая технология Hikvision использует ИИ для классификации объектов?',
                'subtitle_text' => 'Какая технология Hikvision использует <span class="hl">ИИ</span> для классификации объектов?',
                'video_question' => '/videos/q5.mp4',
                'option_a' => 'AcuSense',
                'option_b' => 'ColorVu',
                'option_c' => 'DarkFighter',
                'correct_answer' => 'A',
            ],
            [
                'question_text' => 'Какой протокол используется для интеграции камер Hikvision с другими системами?',
                'subtitle_text' => 'Какой протокол используется для интеграции камер <span class="hl">Hikvision</span> с другими системами?',
                'video_question' => '/videos/q6.mp4',
                'option_a' => 'Zigbee',
                'option_b' => 'ONVIF',
                'option_c' => 'Bluetooth',
                'correct_answer' => 'B',
            ],
            [
                'question_text' => 'Какое максимальное разрешение поддерживают камеры Hikvision серии Pro?',
                'subtitle_text' => 'Какое максимальное разрешение поддерживают камеры серии <span class="hl">Pro</span>?',
                'video_question' => '/videos/q7.mp4',
                'option_a' => '4 Мп',
                'option_b' => '12 Мп',
                'option_c' => '32 Мп',
                'correct_answer' => 'C',
            ],
            [
                'question_text' => 'Что такое Smart Hybrid Light в камерах Hikvision?',
                'subtitle_text' => 'Что такое <span class="hl">Smart Hybrid Light</span> в камерах Hikvision?',
                'video_question' => '/videos/q8.mp4',
                'option_a' => 'ИК + белая подсветка с автопереключением',
                'option_b' => 'Солнечная панель для питания',
                'option_c' => 'Лазерная подсветка',
                'correct_answer' => 'A',
            ],
            [
                'question_text' => 'Какой кодек сжатия видео наиболее эффективен в камерах Hikvision?',
                'subtitle_text' => 'Какой кодек сжатия видео наиболее эффективен в камерах <span class="hl">Hikvision</span>?',
                'video_question' => '/videos/q9.mp4',
                'option_a' => 'H.264',
                'option_b' => 'H.265+',
                'option_c' => 'MJPEG',
                'correct_answer' => 'B',
            ],
            [
                'question_text' => 'Какое приложение Hikvision используется для удалённого просмотра камер?',
                'subtitle_text' => 'Какое приложение <span class="hl">Hikvision</span> используется для удалённого просмотра камер?',
                'video_question' => '/videos/q10.mp4',
                'option_a' => 'Hik-Connect',
                'option_b' => 'Hik-View',
                'option_c' => 'Hik-Remote',
                'correct_answer' => 'A',
            ],
        ];

        foreach ($questions as $q) {
            Question::create($q);
        }

        // Реакции
        $reactions = [
            ['type' => 'correct', 'video_path' => '/videos/correct-1.mp4', 'subtitle_text' => 'Отлично! Правильный ответ!'],
            ['type' => 'correct', 'video_path' => '/videos/correct-2.mp4', 'subtitle_text' => 'Верно! Так держать!'],
            ['type' => 'correct', 'video_path' => '/videos/correct-3.mp4', 'subtitle_text' => 'Молодец! Всё правильно!'],
            ['type' => 'wrong', 'video_path' => '/videos/wrong-1.mp4', 'subtitle_text' => 'К сожалению, неверно.'],
            ['type' => 'wrong', 'video_path' => '/videos/wrong-2.mp4', 'subtitle_text' => 'Нет, это неправильный ответ.'],
            ['type' => 'wrong', 'video_path' => '/videos/wrong-3.mp4', 'subtitle_text' => 'Увы, ответ неверный.'],
        ];

        foreach ($reactions as $r) {
            Reaction::create($r);
        }
    }
}
