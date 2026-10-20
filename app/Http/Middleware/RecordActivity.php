<?php

namespace App\Http\Middleware;

use App\Services\ActivityRecorder;
use Closure;
use Illuminate\Http\Request;

class RecordActivity
{
    public function handle(Request $request, Closure $next)
    {
        $recorder = app(ActivityRecorder::class);
        $user = $request->user();
        $recorder->start();
        try {
            return $next($request);
        } finally {
            try {
                // Preserve committed changes even if a later operation fails.
                $recorder->flush($request, $user ?? $request->user());
            } finally {
                $recorder->stop();
            }
        }
    }
}
