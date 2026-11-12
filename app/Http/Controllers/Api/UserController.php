<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Audit;
use App\Models\TechnicalChief;
use App\Models\TechnicalChiefName;
use App\Models\Technician;
use App\Models\User;
use App\Models\Report;
use App\Services\StaffAssignments;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index(): JsonResponse
    {
        $users = User::with('nombres')->orderBy('id')->get()->map(fn (User $user) => [
            ...$user->profileSummary(), 'active' => $user->activo, 'sector' => $user->direccion ?? '',
        ]);

        return response()->json(['success' => true, 'data' => $users]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nombre' => 'required|string|max:100',
            'apellido' => 'nullable|string|max:100',
            'email' => 'required|string|email|max:150',
            'password' => ['bail', 'required', 'string', 'min:8', 'regex:/\A[^\x00]{1,72}\z/'],
            'role' => 'required|in:empleado,tecnico,tecnico_lider,administrador',
            'specialty' => 'required_if:role,tecnico,tecnico_lider|nullable|in:Hardware,Software,Redes/Infraestructura',
        ], [
            'required' => 'Completa el campo :attribute.',
            'email.email' => 'Ingresa un correo válido.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.regex' => 'La contraseña debe tener como máximo 72 bytes y no puede contener caracteres nulos.',
            'role.in' => 'Selecciona uno de los cuatro roles disponibles.',
            'specialty.required_if' => 'Selecciona la especialidad técnica.',
            'specialty.in' => 'Selecciona una especialidad válida.',
        ], ['password' => 'contraseña', 'email' => 'correo', 'role' => 'rol']);

        $name = trim($data['nombre'].' '.($data['apellido'] ?? ''));
        if (mb_strlen($name) > 150) {
            throw ValidationException::withMessages(['nombre' => 'El nombre completo admite hasta 150 caracteres.']);
        }
        $passwordHash = Hash::make($data['password']);
        $user = DB::transaction(function () use ($data, $name, $passwordHash, $request) {
            $actor = User::lockForUpdate()->findOrFail($request->user()->id);
            abort_unless($actor->activo, 403);
            try {
                $user = User::create([
                    'nombre_completo' => $name, 'email' => mb_strtolower($data['email']),
                    'password' => $passwordHash, 'activo' => true,
                    'rol' => $data['role'] === 'administrador' ? 'Administrador' : 'Empleado',
                ]);
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages(['email' => 'Ya existe una cuenta con ese correo.']);
            }
            $names = ['nombre' => $data['nombre'], 'apellido' => $data['apellido'] ?? null];
            $user->nombres()->create($names);
            if (in_array($data['role'], ['tecnico', 'tecnico_lider'], true)) {
                Technician::create(['id' => $user->id, 'tipo_tecnico' => $data['specialty']]);
            }
            if ($data['role'] === 'tecnico_lider') {
                TechnicalChief::create(['id' => $user->id, 'nombre_completo' => $name]);
                TechnicalChiefName::create(['jefe_id' => $user->id, ...$names]);
                Report::open()->whereNull('jefe_id')->update(['jefe_id' => $user->id]);
            }
            Audit::create(['fecha_auditoria' => now(), 'descripcion' => "Alta de cuenta {$user->id} ({$data['role']}) por usuario {$actor->id}."]);

            return $user;
        }, 3);

        return response()->json(['success' => true, 'message' => 'La cuenta ya puede iniciar sesión.', 'data' => ['id' => $user->id]], 201);
    }

    public function destroy(Request $request, int $id, StaffAssignments $assignments): JsonResponse
    {
        $actorId = $request->user()->id;
        if ($actorId === $id) {
            throw ValidationException::withMessages(['user' => 'Otro administrador o jefe técnico debe dar de baja tu cuenta.']);
        }
        $impact = DB::transaction(function () use ($actorId, $id, $assignments) {
            // Orden común para bajas simultáneas entre dos responsables.
            $accounts = User::whereKey([$actorId, $id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            abort_unless($accounts->get($actorId)?->activo, 403);
            $target = $accounts->get($id);
            abort_unless($target, 404);
            if (! $target->activo) return null;

            $target->forceFill(['activo' => false, 'remember_token' => null])->save();
            DB::table('sessions')->where('user_id', $id)->delete();
            $impact = $assignments->release($target);
            Audit::create(['fecha_auditoria' => now(), 'descripcion' => "Baja de cuenta {$id} por usuario {$actorId}."]);
            return $impact;
        }, 3);

        return response()->json(['success' => true, 'message' => 'La cuenta quedó deshabilitada.', 'data' => $impact]);
    }
}
