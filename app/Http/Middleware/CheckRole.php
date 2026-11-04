<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = Auth::user() ?? $request->user();

        // 3. SEGURIDAD: Si no hay usuario autenticado, NO asumir ningún usuario predeterminado.
        // Se deniega de inmediato con HTTP 401 Unauthorized.
        if (!$user) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'error' => 'No autenticado. Debe iniciar sesión en PreventiTI para acceder a este recurso.',
                ], 401);
            }
            return redirect()->guest('/login');
        }

        // 4. Verificación de estado de cuenta
        if (!$user->activo) {
            Auth::logout();
            session()->invalidate();
            return response()->json([
                'success' => false,
                'error' => 'Cuenta de usuario deshabilitada. Contacte al administrador del sistema.',
            ], 403);
        }

        $userRole = $user->roleName();

        // 5. Control estricto de roles y jerarquía
        if (!empty($roles) && !in_array($userRole, $roles)) {
            return response()->json([
                'success' => false,
                'error' => 'Acceso Denegado (HTTP 403). No posee los permisos requeridos para esta operación.',
                'user_role' => $userRole,
                'hierarchy_level' => $user->getHierarchyLevel(),
                'required_roles' => $roles,
            ], 403);
        }

        return $next($request);
    }
}
