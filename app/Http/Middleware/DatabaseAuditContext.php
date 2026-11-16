<?php

namespace App\Http\Middleware;

use App\Services\DatabaseWorkflow;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DatabaseAuditContext
{
    public function handle(Request $request, Closure $next): Response
    {
        return DatabaseWorkflow::context($request->user(), null, fn () => $next($request));
    }
}
