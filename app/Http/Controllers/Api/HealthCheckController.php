<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Event;
use Throwable;

class HealthCheckController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $exception = null;

        try {
            Event::dispatch(new DiagnosingHealth);
        } catch (Throwable $e) {
            if (app()->hasDebugModeEnabled()) {
                throw $e;
            }

            report($e);
            $exception = $e;
        }

        return response()->json([
            'status' => $exception ? 'down' : 'up',
        ], $exception ? 500 : 200);
    }
}
