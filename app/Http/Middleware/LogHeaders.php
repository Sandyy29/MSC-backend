<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LogHeaders
{
    public function handle(Request $request, Closure $next)
    {
        Log::info('Request to ' . $request->path(), $request->headers->all());
        return $next($request);
    }
}
