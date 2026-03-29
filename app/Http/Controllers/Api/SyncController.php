<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Caller;
use App\Models\EmergencyAlert;
use App\Models\QueueItem;
use App\Models\ServicePoint;
use App\Models\VoiceConfiguration;
use Illuminate\Http\Request;

class SyncController extends Controller
{
    public function syncQueue(Request $request)
    {
        $validated = $request->validate([
            'uuid'             => 'required|string',
            'business_id'      => 'required|integer',
            'service_point_id' => 'required|integer',
            'visit_id'         => 'nullable|string',
            'client_name'      => 'nullable|string',
            'status'           => 'required|string',
            'priority'         => 'nullable|string',
            'queued_at'        => 'nullable|date',
        ]);

        $item = QueueItem::where('uuid', $validated['uuid'])->first();

        // Prevent background syncs from accidentally reverting a 'serving' client back to 'pending'
        if ($item && $item->status === 'serving' && $validated['status'] === 'pending') {
            if (!$request->input('is_force')) {
                $validated['status'] = 'serving';
            }
        }

        $item = QueueItem::updateOrCreate(
            ['uuid' => $validated['uuid']],
            [
                'business_id'          => $validated['business_id'],
                'service_point_id'     => $validated['service_point_id'],
                'visit_id'             => $validated['visit_id'] ?? null,
                'client_name'          => $validated['client_name'] ?? null,
                'status'               => $validated['status'],
                'priority'             => $validated['priority'] ?? 'normal',
                'queued_at'            => $validated['queued_at'] ?? null,
                'updated_in_master_at' => now(),
            ]
        );
 
        return response()->json(['message' => 'Queue item synced successfully.', 'data' => $item]);
    }

    public function deleteQueue(string $uuid)
    {
        QueueItem::where('uuid', $uuid)->delete();
        return response()->json(['message' => 'Queue item deleted.']);
    }

    /**
     * Upsert a caller and its assigned service points.
     *
     * Payload:
     *   kashtre_id, business_id, name, status, display_token?,
     *   announcement_message?, speech_rate?, speech_volume?,
     *   service_points: [{ kashtre_id, name }]
     */
    public function syncCaller(Request $request)
    {
        $validated = $request->validate([
            'kashtre_id'                  => 'required|integer',
            'business_id'                 => 'required|integer',
            'name'                        => 'required|string|max:255',
            'status'                      => 'required|string',
            'display_token'               => 'nullable|string|max:10',
            'announcement_message'        => 'nullable|string',
            'speech_rate'                 => 'nullable|numeric',
            'speech_volume'               => 'nullable|numeric',
            'service_points'              => 'nullable|array',
            'service_points.*.kashtre_id' => 'required|integer',
            'service_points.*.name'       => 'required|string',
        ]);

        $caller = Caller::updateOrCreate(
            ['kashtre_id' => $validated['kashtre_id']],
            [
                'business_id'          => $validated['business_id'],
                'name'                 => $validated['name'],
                'status'               => $validated['status'],
                'display_token'        => $validated['display_token'] ?? null,
                'announcement_message' => $validated['announcement_message'] ?? null,
                'speech_rate'          => $validated['speech_rate'] ?? 1.0,
                'speech_volume'        => $validated['speech_volume'] ?? 1.0,
            ]
        );

        // Upsert each service point by its kashtre_id, then sync the pivot
        $localSpIds = [];
        foreach ($validated['service_points'] ?? [] as $sp) {
            $localSp = ServicePoint::updateOrCreate(
                ['kashtre_id' => $sp['kashtre_id']],
                [
                    'business_id' => $validated['business_id'],
                    'name'        => $sp['name'],
                ]
            );
            $localSpIds[] = $localSp->id;
        }
        $caller->servicePoints()->sync($localSpIds);

        return response()->json(['message' => 'Caller synced.', 'data' => $caller]);
    }

    /**
     * Remove a caller from the calling service by its kashtre ID.
     */
    public function deleteCaller(int $kashtre_id)
    {
        $caller = Caller::where('kashtre_id', $kashtre_id)->first();

        if ($caller) {
            $caller->servicePoints()->detach();
            $caller->delete();
        }

        return response()->json(['message' => 'Caller removed.']);
    }

    /**
     * Upsert TTS/voice configuration for a business.
     *
     * Payload:
     *   business_id, tts_voice_id, tts_voice_name, tts_stability,
     *   tts_similarity_boost, tts_speed, announcement_message, is_active
     */
    public function syncVoiceConfig(Request $request)
    {
        $validated = $request->validate([
            'business_id'          => 'required|integer',
            'tts_voice_id'         => 'nullable|string|max:255',
            'tts_voice_name'       => 'nullable|string|max:255',
            'tts_stability'        => 'nullable|numeric|min:0|max:1',
            'tts_similarity_boost' => 'nullable|numeric|min:0|max:1',
            'tts_speed'            => 'nullable|numeric|min:0.25|max:4',
            'announcement_message'               => 'nullable|string|max:500',
            'is_active'                          => 'boolean',
            'emergency_repeat_count'             => 'nullable|integer|min:1|max:10',
            'emergency_repeat_interval'          => 'nullable|integer|min:0|max:60',
            'emergency_display_duration'         => 'nullable|integer|min:0|max:3600',
            'emergency_tts_voice_id'             => 'nullable|string|max:255',
            'emergency_tts_voice_name'           => 'nullable|string|max:255',
            'emergency_tts_stability'            => 'nullable|numeric|min:0|max:1',
            'emergency_tts_similarity_boost'     => 'nullable|numeric|min:0|max:1',
            'emergency_tts_speed'                => 'nullable|numeric|min:0.25|max:4',
        ]);

        $config = VoiceConfiguration::updateOrCreate(
            ['business_id' => $validated['business_id']],
            [
                'tts_voice_id'                   => $validated['tts_voice_id'] ?? null,
                'tts_voice_name'                 => $validated['tts_voice_name'] ?? null,
                'tts_stability'                  => $validated['tts_stability'] ?? 0.5,
                'tts_similarity_boost'           => $validated['tts_similarity_boost'] ?? 0.75,
                'tts_speed'                      => $validated['tts_speed'] ?? 1.0,
                'announcement_message'           => $validated['announcement_message'] ?? null,
                'is_active'                      => $validated['is_active'] ?? true,
                'emergency_repeat_count'         => $validated['emergency_repeat_count'] ?? 3,
                'emergency_repeat_interval'      => $validated['emergency_repeat_interval'] ?? 5,
                'emergency_display_duration'     => $validated['emergency_display_duration'] ?? 0,
                'emergency_tts_voice_id'         => $validated['emergency_tts_voice_id'] ?? null,
                'emergency_tts_voice_name'       => $validated['emergency_tts_voice_name'] ?? null,
                'emergency_tts_stability'        => $validated['emergency_tts_stability'] ?? 0.5,
                'emergency_tts_similarity_boost' => $validated['emergency_tts_similarity_boost'] ?? 0.75,
                'emergency_tts_speed'            => $validated['emergency_tts_speed'] ?? 1.0,
            ]
        );

        return response()->json(['message' => 'Voice config synced.', 'data' => $config]);
    }

    public function syncEmergency(Request $request)
    {
        $validated = $request->validate([
            'business_id'        => 'required|integer',
            'service_point_name' => 'nullable|string',
            'message'            => 'required|string',
            'display_message'    => 'nullable|string',
            'color'              => 'nullable|string',
            'triggered_at'       => 'required|date',
        ]);

        // Resolve any existing active alert for this business
        EmergencyAlert::where('business_id', $validated['business_id'])
            ->where('is_active', true)
            ->update(['is_active' => false, 'resolved_at' => now()]);

        $alert = EmergencyAlert::create([
            'business_id'        => $validated['business_id'],
            'service_point_name' => $validated['service_point_name'],
            'message'            => $validated['message'],
            'display_message'    => $validated['display_message'] ?? null,
            'is_active'          => true,
            'triggered_at'       => $validated['triggered_at'],
        ]);

        return response()->json(['message' => 'Emergency synced.', 'data' => $alert]);
    }

    public function resolveEmergency(Request $request)
    {
        $validated = $request->validate([
            'business_id' => 'required|integer',
        ]);

        EmergencyAlert::where('business_id', $validated['business_id'])
            ->where('is_active', true)
            ->update(['is_active' => false, 'resolved_at' => now()]);

        return response()->json(['message' => 'Emergency resolved.']);
    }

    public function syncAnnouncement(Request $request)
    {
        $validated = $request->validate([
            'business_id'  => 'required|integer',
            'message'      => 'required|string',
            'type'         => 'nullable|string',
            'triggered_at' => 'required|date',
        ]);

        // Resolve any existing active announcement for this business
        Announcement::where('business_id', $validated['business_id'])
            ->where('is_active', true)
            ->update(['is_active' => false, 'resolved_at' => now()]);

        $announcement = Announcement::create([
            'business_id'  => $validated['business_id'],
            'message'      => $validated['message'],
            'type'         => $validated['type'] ?? 'broadcast',
            'is_active'    => true,
            'triggered_at' => $validated['triggered_at'],
        ]);

        return response()->json(['message' => 'Announcement synced.', 'data' => $announcement]);
    }

    public function resolveAnnouncement(Request $request)
    {
        $validated = $request->validate([
            'business_id' => 'required|integer',
        ]);

        Announcement::where('business_id', $validated['business_id'])
            ->where('is_active', true)
            ->update(['is_active' => false, 'resolved_at' => now()]);

        return response()->json(['message' => 'Announcement resolved.']);
    }
}
