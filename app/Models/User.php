<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected $fillable = [
        'telegram_id',
        'phone',
        'first_name',
        'last_name',
        'email',
        'code',
        'code_used',
    ];

    protected function casts(): array
    {
        return [
            'code_used' => 'boolean',
        ];
    }

    public function quizSessions()
    {
        return $this->hasMany(QuizSession::class);
    }
}
