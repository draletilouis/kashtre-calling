<?php

use App\Http\Controllers\Api\DisplayController;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;

require dirname(__DIR__, 3) . '/vendor/autoload.php';

$app = require dirname(__DIR__, 3) . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$request = Request::capture();
$request->headers->set('Accept', 'application/json');

$logId = (int) $request->query('log_id', 0);

if ($logId < 1) {
    response()->json(['error' => 'log_id is required.'], 400)->withHeaders([
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Methods' => 'GET, OPTIONS',
        'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With',
    ])->send();
    exit;
}

$response = app(DisplayController::class)->streamAudio($request, $logId);
$response->send();
