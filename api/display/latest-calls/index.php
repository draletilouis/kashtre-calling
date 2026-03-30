<?php

use App\Http\Controllers\Api\DisplayController;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;

require dirname(__DIR__, 3) . '/vendor/autoload.php';

$app = require dirname(__DIR__, 3) . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$request = Request::capture();
$request->headers->set('Accept', 'application/json');

$response = app(DisplayController::class)->latestCalls($request);
$response->send();
