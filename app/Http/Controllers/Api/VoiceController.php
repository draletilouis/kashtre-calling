<?php

namespace App\Http\Controllers\Api;

use App\Contracts\TTSProvider;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VoiceController extends Controller
{
    public function __construct(private TTSProvider $tts) {}

    /**
     * Return the list of available ElevenLabs voices.
     */
    public function index()
    {
        try {
            $voices = $this->tts->getVoices();
            return response()->json($voices);
        } catch (\Throwable $e) {
            Log::error('VoiceController::index failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Could not fetch voices from TTS provider.'], 502);
        }
    }

    /**
     * Stream a preview of the given voice.
     *
     * Query params: voice_id, text?, stability?, similarity_boost?, speed?
     */
    public function preview(Request $request)
    {
        $voiceId         = $request->query('voice_id');
        $text            = $request->query('text', 'Hello. This is a preview of the selected voice.');
        $stability       = (float) $request->query('stability', 0.5);
        $similarityBoost = (float) $request->query('similarity_boost', 0.75);
        $speed           = (float) $request->query('speed', 1.0);

        if (!$voiceId) {
            return response()->json(['error' => 'voice_id is required.'], 400);
        }

        try {
            return $this->tts->streamAudio($text, $voiceId, $stability, $similarityBoost, $speed);
        } catch (\Throwable $e) {
            Log::error('VoiceController::preview failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Audio preview failed.'], 502);
        }
    }
}
