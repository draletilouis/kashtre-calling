<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CallLog;
use App\Models\Caller;
use Illuminate\Http\Request;

class AnnounceController extends Controller
{
    public function trigger(Request $request)
    {
        $validated = $request->validate([
            'caller_id'          => 'required|integer',
            'kashtre_log_id'     => 'required|integer',
            'visit_id'           => 'nullable|string',
            'client_name'        => 'nullable|string',
            'service_point_name' => 'nullable|string',
            'room_name'          => 'nullable|string',
            'audio_enabled'      => 'boolean',
            'video_enabled'      => 'boolean',
        ]);

        $caller = Caller::where('kashtre_id', $validated['caller_id'])->first();

        if (!$caller) {
            return response()->json(['error' => 'Caller not found on the calling service. Ensure callers are synced.'], 404);
        }

        $log = CallLog::create([
            'caller_id'          => $caller->id,
            'kashtre_log_id'     => $validated['kashtre_log_id'],
            'visit_id'           => $validated['visit_id'],
            'client_name'        => $validated['client_name'],
            'service_point_name' => $validated['service_point_name'],
            'room_name'          => $validated['room_name'],
            'audio_enabled'      => $validated['audio_enabled'] ?? true,
            'video_enabled'      => $validated['video_enabled'] ?? true,
            'called_at'          => now(),
        ]);

        return response()->json(['message' => 'Announcement triggered.', 'data' => $log]);
    }
}
