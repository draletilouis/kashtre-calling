<?php

namespace App\Services\TTS;

use App\Contracts\TTSProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ElevenLabsProvider implements TTSProvider
{
    protected string $apiKey;
    protected string $baseUrl;
    protected int    $timeout;

    public function __construct()
    {
        $this->apiKey  = config('services.elevenlabs.api_key', '');
        $this->baseUrl = rtrim(config('services.elevenlabs.api_url', 'https://api.elevenlabs.io/v1'), '/');
        $this->timeout = (int) config('services.elevenlabs.timeout', 30);
    }

    /**
     * Fetch the list of available voices from ElevenLabs.
     *
     * @return array  Each item: ['voice_id', 'name', 'preview_url', 'category']
     * @throws \RuntimeException on API failure
     */
    public function getVoices(): array
    {
        $response = Http::withHeaders(['xi-api-key' => $this->apiKey])
            ->timeout($this->timeout)
            ->withoutVerifying()
            ->get("{$this->baseUrl}/voices");

        if (!$response->successful()) {
            Log::error('ElevenLabs getVoices failed', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            throw new \RuntimeException('Failed to fetch voices from ElevenLabs (HTTP ' . $response->status() . ').');
        }

        $voices = $response->json('voices', []);

        return array_map(fn($v) => [
            'voice_id'    => $v['voice_id']    ?? '',
            'name'        => $v['name']         ?? '',
            'preview_url' => $v['preview_url']  ?? null,
            'category'    => $v['category']     ?? 'premade',
        ], $voices);
    }

    /**
     * Stream TTS audio from ElevenLabs for the given text and voice settings.
     *
     * Returns a StreamedResponse with Content-Type audio/mpeg so it can be
     * piped directly to the browser or the display app.
     *
     * @throws \RuntimeException on API failure
     */
    public function streamAudio(
        string $text,
        string $voiceId,
        float  $stability       = 0.5,
        float  $similarityBoost = 0.75,
        float  $speed           = 1.0
    ): StreamedResponse {
        $url = "{$this->baseUrl}/text-to-speech/{$voiceId}/stream";

        Log::debug('ElevenLabs streamAudio request', [
            'url'     => $url,
            'key_len' => strlen($this->apiKey),
            'key_start' => substr($this->apiKey, 0, 4),
            'key_end'   => substr($this->apiKey, -4),
        ]);

        $response = Http::withHeaders([
                'xi-api-key'   => $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept'       => 'audio/mpeg',
            ])
            ->timeout($this->timeout)
            ->withoutVerifying()
            ->withOptions(['stream' => true])
            ->post($url, [
                'text'          => $text,
                'model_id'      => 'eleven_multilingual_v2',
                'voice_settings' => [
                    'stability'        => $stability,
                    'similarity_boost' => $similarityBoost,
                    'speed'            => $speed,
                ],
            ]);

        if (!$response->successful()) {
            Log::error('ElevenLabs streamAudio failed', [
                'voice_id' => $voiceId,
                'status'   => $response->status(),
                'body'     => $response->body(),
            ]);
            throw new \RuntimeException('ElevenLabs audio generation failed (HTTP ' . $response->status() . ').');
        }

        $body = $response->toPsrResponse()->getBody();

        return new StreamedResponse(function () use ($body) {
            while (!$body->eof()) {
                echo $body->read(4096);
                flush();
            }
        }, 200, [
            'Content-Type'        => 'audio/mpeg',
            'Cache-Control'       => 'no-store',
            'X-Accel-Buffering'   => 'no',
        ]);
    }
}
