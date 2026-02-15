<?php

namespace App\Http\Controllers;

use App\Models\User;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function page()
    {
        $usersCount = User::count();
        return view('export', compact('usersCount'));
    }

    public function users()
    {
        $users = User::with(['quizSessions' => function ($q) {
            $q->latest('finished_at');
        }])->get();

        $response = new StreamedResponse(function () use ($users) {
            $handle = fopen('php://output', 'w');

            // BOM для корректного отображения кириллицы в Excel
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'ID',
                'Telegram ID',
                'Телефон',
                'Имя',
                'Фамилия',
                'Email',
                'Код',
                'Код использован',
                'Лучший результат',
                'Кол-во игр',
                'Дата регистрации',
            ], ';');

            foreach ($users as $user) {
                $bestScore = $user->quizSessions->max('score');
                $gamesCount = $user->quizSessions->count();

                fputcsv($handle, [
                    $user->id,
                    $user->telegram_id,
                    $user->phone,
                    $user->first_name,
                    $user->last_name,
                    $user->email,
                    $user->code,
                    $user->code_used ? 'Да' : 'Нет',
                    $bestScore !== null ? $bestScore . '/5' : 'Не играл',
                    $gamesCount,
                    $user->created_at?->format('d.m.Y H:i'),
                ], ';');
            }

            fclose($handle);
        });

        $filename = 'users_' . date('Y-m-d_H-i') . '.csv';

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

        return $response;
    }
}
