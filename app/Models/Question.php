<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    protected $fillable = [
        'question_text',
        'subtitle_text',
        'option_a',
        'option_b',
        'option_c',
        'correct_answer',
        'video_question',
    ];
}
