<?php

namespace App\Http\Controllers\Api;

use App\Contracts\TTSProvider;
use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\CallLog;
use App\Models\Caller;
use App\Models\EmergencyAlert;
use App\Models\QueueItem;
use App\Models\VoiceConfiguration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DisplayController extends Controller
{
    public function latestCalls(Request $request)
    {
        $token = $request->query('token');

        if (!$token) {
            return response()->json(['error' => 'Token required.'], 400);
        }

        $caller = Caller::where('display_token', $token)
            ->where('status', 'active')
            ->first();

        if (!$caller) {
            return response()->json(['error' => 'Invalid or inactive display token.'], 404);
        }

        $query = CallLog::where('caller_id', $caller->id)
            ->whereDate('called_at', today());

        $after = $request->query('after');
        if ($after !== null && is_numeric($after)) {
            $query->where('id', '>', (int) $after);
        }

        $logs = $query->orderByDesc('id')->limit(10)->get()->reverse()->values()->map(fn ($log) => [
            'log_id'        => $log->id,
            'visit_id'      => $log->visit_id ?? $log->client_name ?? null,
            'service_point' => $log->service_point_name,
            'room'          => $log->room_name,
            'called_at'     => $log->called_at->format('H:i:s'),
            'audio_enabled' => (bool) $log->audio_enabled,
            'video_enabled' => (bool) $log->video_enabled,
        ]);

        // QueueItem.service_point_id stores the master app's SP ID (kashtre_id), not the local auto-increment id.
        $servicePointKashtreIds = $caller->servicePoints()->pluck('service_points.kashtre_id');

        // Build a kashtre_id → name lookup for service points
        $spNames = \App\Models\ServicePoint::whereIn('kashtre_id', $servicePointKashtreIds)
            ->pluck('name', 'kashtre_id');

        $queue = QueueItem::whereIn('service_point_id', $servicePointKashtreIds)
            ->where('status', 'pending')
            ->orderBy('queued_at')
            ->get()
            ->values()
            ->map(fn ($q, $i) => [
                'position'      => $i + 1,
                'visit_id'      => $q->visit_id ?? '—',
                'service_point' => $spNames[$q->service_point_id] ?? '—',
                'queued_at'     => $q->queued_at ? $q->queued_at->format('H:i') : '—',
                'priority'      => $q->priority ?? 'normal',
            ]);

        // Who is actively being served right now (all connected service points)
        $serving = QueueItem::whereIn('service_point_id', $servicePointKashtreIds)
            ->where('status', 'serving')
            ->orderBy('updated_in_master_at', 'desc')
            ->get();

        // Look up the room name for each serving client from their most recent CallLog
        $servingVisitIds = $serving->pluck('visit_id')->filter()->unique();
        $roomNames = CallLog::where('caller_id', $caller->id)
            ->whereIn('visit_id', $servingVisitIds)
            ->orderBy('called_at', 'desc')
            ->get()
            ->unique('visit_id')
            ->pluck('room_name', 'visit_id');

        $nowServing = $serving->map(fn($s) => [
            'visit_id'      => $s->visit_id ?? $s->client_name ?? '—',
            'service_point' => $roomNames[$s->visit_id] ?? $spNames[$s->service_point_id] ?? '—',
        ])->toArray();

        $activeEmergency = EmergencyAlert::where('business_id', $caller->business_id)
            ->where('is_active', true)
            ->latest('triggered_at')
            ->first();

        $emergencyData = $activeEmergency ? [
            'id'                 => $activeEmergency->id,
            'message'            => $activeEmergency->message,
            'display_message'    => $activeEmergency->display_message ?? $activeEmergency->message,
            'service_point_name' => $activeEmergency->service_point_name,
            'triggered_at'       => $activeEmergency->triggered_at->format('H:i:s'),
        ] : null;

        $activeAnnouncement = Announcement::where('business_id', $caller->business_id)
            ->where('is_active', true)
            ->latest('triggered_at')
            ->first();

        $announcementData = $activeAnnouncement ? [
            'id'           => $activeAnnouncement->id,
            'message'      => $activeAnnouncement->message,
            'type'         => $activeAnnouncement->type,
            'triggered_at' => $activeAnnouncement->triggered_at->format('H:i:s'),
        ] : null;

        $voiceConfig = VoiceConfiguration::where('business_id', $caller->business_id)->first();

        return response()->json([
            'caller_name'         => $caller->name,
            'logs'                => $logs,
            'queue'               => $queue,
            'now_serving'         => $nowServing,
            'active_emergency'    => $emergencyData,
            'active_announcement' => $announcementData,
            'emergency_config'    => [
                'repeat_count'    => $voiceConfig ? ($voiceConfig->emergency_repeat_count ?? 3) : 3,
                'repeat_interval' => $voiceConfig ? ($voiceConfig->emergency_repeat_interval ?? 5) : 5,
                'display_duration'=> $voiceConfig ? ($voiceConfig->emergency_display_duration ?? 0) : 0,
            ],
        ])->header('Access-Control-Allow-Origin', '*');
    }

    public function streamAudio(Request $request, int $logId)
    {
        $token = $request->query('token');

        $caller = Caller::where('display_token', $token)
            ->where('status', 'active')
            ->first();

        if (!$caller) {
            return response()->json(['error' => 'Invalid or inactive display token.'], 404);
        }

        $log = CallLog::where('id', $logId)
            ->where('caller_id', $caller->id)
            ->first();

        if (!$log) {
            return response()->json(['error' => 'Log not found.'], 404);
        }

        if (!$log->audio_enabled) {
            return response()->json(['error' => 'Audio disabled for this announcement.'], 422);
        }

        $config = VoiceConfiguration::where('business_id', $caller->business_id)->first();

        if (!$config || !$config->tts_voice_id || !$config->is_active) {
            return response()->json(['error' => 'TTS not configured or active for this organisation.'], 422);
        }

        $visitId     = $log->visit_id ?? $log->client_name ?? 'Next client';
        $destination = $log->room_name ?? 'the room';

        $template = $config->announcement_message ?: 'Now serving {name}. Please proceed to {destination}.';

        $text = str_replace(['{name}', '{destination}'], [$visitId, $destination], $template);

        try {
            return app(TTSProvider::class)->streamAudio(
                $text,
                $config->tts_voice_id,
                $config->tts_stability ?? 0.5,
                $config->tts_similarity_boost ?? 0.75,
                $config->tts_speed ?? 1.0
            );
        } catch (\Throwable $e) {
            Log::error('DisplayController::streamAudio failed', [
                'log_id' => $logId,
                'error'  => $e->getMessage(),
            ]);
            return response()->json(['error' => 'Audio generation failed.'], 502);
        }
    }

    public function streamEmergencyAudio(Request $request, int $emergencyId)
    {
        $token = $request->query('token');

        $caller = Caller::where('display_token', $token)
            ->where('status', 'active')
            ->first();

        if (!$caller) {
            return response()->json(['error' => 'Invalid or inactive display token.'], 404);
        }

        $alert = EmergencyAlert::where('id', $emergencyId)
            ->where('business_id', $caller->business_id)
            ->first();

        if (!$alert) {
            return response()->json(['error' => 'Emergency alert not found.'], 404);
        }

        $config = VoiceConfiguration::where('business_id', $caller->business_id)->first();

        if (!$config || !$config->is_active) {
            return response()->json(['error' => 'TTS not configured or active for this organisation.'], 422);
        }

        // Use emergency-specific voice if configured, fall back to regular voice
        $voiceId         = $config->emergency_tts_voice_id         ?? $config->tts_voice_id;
        $stability       = $config->emergency_tts_stability         ?? $config->tts_stability        ?? 0.5;
        $similarityBoost = $config->emergency_tts_similarity_boost  ?? $config->tts_similarity_boost ?? 0.75;
        $speed           = $config->emergency_tts_speed             ?? $config->tts_speed            ?? 1.0;

        if (!$voiceId) {
            return response()->json(['error' => 'TTS not configured or active for this organisation.'], 422);
        }

        try {
            $destination = $alert->service_point_name ?? 'the area';
            $message     = str_replace('{destination}', $destination, $alert->message);

            return app(TTSProvider::class)->streamAudio(
                $message,
                $voiceId,
                $stability,
                $similarityBoost,
                $speed
            );
        } catch (\Throwable $e) {
            Log::error('DisplayController::streamEmergencyAudio failed', [
                'emergency_id' => $emergencyId,
                'error'        => $e->getMessage(),
            ]);
            return response()->json(['error' => 'Audio generation failed.'], 502);
        }
    }

    public function streamAnnouncementAudio(Request $request, int $announcementId)
    {
        $token = $request->query('token');

        $caller = Caller::where('display_token', $token)
            ->where('status', 'active')
            ->first();

        if (!$caller) {
            return response()->json(['error' => 'Invalid or inactive display token.'], 404);
        }

        $announcement = Announcement::where('id', $announcementId)
            ->where('business_id', $caller->business_id)
            ->first();

        if (!$announcement) {
            return response()->json(['error' => 'Announcement not found.'], 404);
        }

        $config = VoiceConfiguration::where('business_id', $caller->business_id)->first();

        if (!$config || !$config->tts_voice_id || !$config->is_active) {
            return response()->json(['error' => 'TTS not configured or active for this organisation.'], 422);
        }

        try {
            return app(TTSProvider::class)->streamAudio(
                $announcement->message,
                $config->tts_voice_id,
                $config->tts_stability ?? 0.5,
                $config->tts_similarity_boost ?? 0.75,
                $config->tts_speed ?? 1.0
            );
        } catch (\Throwable $e) {
            Log::error('DisplayController::streamAnnouncementAudio failed', [
                'announcement_id' => $announcementId,
                'error'           => $e->getMessage(),
            ]);
            return response()->json(['error' => 'Audio generation failed.'], 502);
        }
    }
}
