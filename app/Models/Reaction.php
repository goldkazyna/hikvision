<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reaction extends Model
{
    protected $fillable = [
        'type',
        'video_path',
        'subtitle_text',
    ];
}
