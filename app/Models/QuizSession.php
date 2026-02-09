<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuizSession extends Model
{
    protected $fillable = [
        'user_id',
        'started_at',
        'finished_at',
        'score',
        'answers',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'answers' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
