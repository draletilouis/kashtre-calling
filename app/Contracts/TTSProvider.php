<?php

namespace App\Contracts;

use Symfony\Component\HttpFoundation\StreamedResponse;

interface TTSProvider
{
    /**
     * Return the list of available voices.
     *
     * @return array  Each item: ['voice_id', 'name', 'preview_url', 'category']
     * @throws \RuntimeException on provider failure
     */
    public function getVoices(): array;

    /**
     * Stream TTS audio for the given text and voice settings.
     *
     * @throws \RuntimeException on provider failure
     */
    public function streamAudio(
        string $text,
        string $voiceId,
        float  $stability       = 0.5,
        float  $similarityBoost = 0.75,
        float  $speed           = 1.0
    ): StreamedResponse;
}
