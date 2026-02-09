<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class WhisperController extends Controller
{
    public function transcribe(Request $request)
    {
        $request->validate([
            'audio' => 'required|file',
        ]);

        $audioFile = $request->file('audio');

        $response = Http::withToken(config('services.openai.api_key'))
            ->withOptions(['verify' => false])
            ->attach('file', file_get_contents($audioFile->getRealPath()), 'audio.webm')
            ->post('https://api.openai.com/v1/audio/transcriptions', [
                'model' => 'whisper-1',
                'language' => 'ru',
            ]);

        if ($response->successful()) {
            return response()->json([
                'text' => $response->json('text'),
            ]);
        }

        return response()->json([
            'text' => null,
            'error' => $response->json('error.message', 'Whisper API error'),
        ], 500);
    }
}
