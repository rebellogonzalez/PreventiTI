<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Audit;
use App\Models\Technician;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse|RedirectResponse
    {
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $credentials = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        if (! Auth::attempt([...$credentials, 'activo' => true], $request->boolean('remember'))) {
            $message = 'Correo o contraseña incorrectos, o cuenta deshabilitada.';
            return $request->expectsJson()
                ? response()->json(['success' => false, 'error' => $message], 401)
                : back()->withErrors(['email' => $message])->withInput($request->only('email'));
        }
        $request->session()->regenerate();
        $user = $request->user();
        Audit::create(['fecha_auditoria' => now(), 'descripcion' => "Inicio de sesión de {$user->nombre} con rol {$user->roleName()}."]);
        return $request->expectsJson()
            ? response()->json(['success' => true, ...$user->sessionProfile(), 'redirect' => '/'])
            : redirect()->intended('/');
    }

    public function logout(Request $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        if ($user) Audit::create(['fecha_auditoria' => now(), 'descripcion' => "Cierre de sesión de {$user->nombre}."]);
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return $request->expectsJson()
            ? response()->json(['success' => true, 'redirect' => '/login'])
            : redirect('/login');
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['success' => true, ...$request->user()->sessionProfile()]);
    }

    public function users(Request $request): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $users = User::with('nombres')->orderBy('id')->get()->map(fn (User $user) => [
            ...$user->sessionProfile()['user'], 'active' => $user->activo,
            'sector' => $user->direccion ?? '',
        ]);
        return response()->json(['success' => true, 'data' => $users]);
    }

    public function taskAssignees(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasHierarchyAtLeast(2), 403);
        $users = User::with('nombres')->where('activo', true)->whereIn('id', Technician::select('id'))->orderBy('id')->get();
        return response()->json(['success' => true, 'data' => $users->map(fn (User $user) => [
            'id' => $user->id, 'name' => $user->nombre, 'role_title' => $user->role_title,
        ])]);
    }
}
