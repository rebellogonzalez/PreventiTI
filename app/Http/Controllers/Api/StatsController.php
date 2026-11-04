<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\StatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StatsController extends Controller
{
    public function __construct(private readonly StatsService $statsService) {}

    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (! $user) return response()->json(['success' => false, 'error' => 'No autenticado.'], 401);
        if (! $user->hasHierarchyAtLeast(2)) return response()->json(['success' => false, 'error' => 'Acceso denegado a estadísticas.', 'user_role' => $user->roleName()], 403);
        return response()->json(['success' => true, 'data' => $this->statsService->getStatsForUser($user)]);
    }
}
