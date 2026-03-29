<?php

use App\Http\Controllers\Api\AnnounceController;
use App\Http\Controllers\Api\DisplayController;
use App\Http\Controllers\Api\SyncController;
use App\Http\Controllers\Api\VoiceController;
use Illuminate\Support\Facades\Route;

// Voice / TTS Routes (proxied from Kashtre settings UI)
Route::get('/voices', [VoiceController::class, 'index']);
Route::get('/voice-preview', [VoiceController::class, 'preview']);

// Standalone Display Routes
Route::get('/display/latest-calls', [DisplayController::class, 'latestCalls']);
Route::get('/display/audio/{logId}', [DisplayController::class, 'streamAudio']);
Route::get('/display/emergency-audio/{emergencyId}', [DisplayController::class, 'streamEmergencyAudio']);
Route::get('/display/announcement-audio/{announcementId}', [DisplayController::class, 'streamAnnouncementAudio']);

// Inbound Sync Routes from Kashtre (Master -> Slave)
// Protected by shared secret: Authorization: Bearer <CALLING_SERVICE_SYNC_SECRET>
Route::prefix('v1/sync')->middleware('sync.auth')->group(function () {
    Route::post('/queue', [SyncController::class, 'syncQueue']);
    Route::delete('/queue/{uuid}', [SyncController::class, 'deleteQueue']);
    Route::post('/announce', [AnnounceController::class, 'trigger']);
    Route::post('/callers', [SyncController::class, 'syncCaller']);
    Route::delete('/callers/{kashtre_id}', [SyncController::class, 'deleteCaller']);
    Route::post('/voice-config', [SyncController::class, 'syncVoiceConfig']);
    Route::post('/emergency', [SyncController::class, 'syncEmergency']);
    Route::post('/emergency/resolve', [SyncController::class, 'resolveEmergency']);
    Route::post('/announcement', [SyncController::class, 'syncAnnouncement']);
    Route::post('/announcement/resolve', [SyncController::class, 'resolveAnnouncement']);
});
