<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Audit;
use App\Services\BackupService;
use App\Services\InfrastructureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

// Se conserva el nombre de clase y las rutas para mantener compatibilidad.
class NetworkSimulationController extends Controller
{
    public function status(InfrastructureService $infrastructure, BackupService $backups): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $infrastructure->status($backups)])->header('Cache-Control', 'no-store');
    }

    public function scan(Request $request, InfrastructureService $infrastructure): JsonResponse
    {
        $request->validate(['origin' => 'prohibited', 'host' => 'prohibited', 'ports' => 'prohibited'], ['prohibited' => 'El destino se configura en el servidor. La comprobación se ejecuta desde el servidor de la aplicación.']);
        try {
            return response()->json(['success' => true, 'data' => $infrastructure->scan()]);
        } catch (RuntimeException $error) {
            return response()->json(['success' => false, 'error' => $error->getMessage()], 503);
        }
    }

    public function drpBackup(Request $request, BackupService $backups): JsonResponse
    {
        try {
            $backup = $backups->create();
        } catch (Throwable $error) {
            report($error);
            return response()->json(['success' => false, 'error' => $error instanceof RuntimeException ? $error->getMessage() : 'No se pudo completar y verificar el respaldo. Revise los permisos y extensiones del servidor.'], 503);
        }
        $warning = null;
        try {
            Audit::create(['fecha_auditoria' => now(), 'descripcion' => "Respaldo real {$backup['filename']} creado por {$request->user()->nombre}. {$backup['bytes']} bytes. SHA-256: {$backup['sha256']}. Copia SQLite y archivos verificados."]);
        } catch (Throwable $error) {
            report($error);
            $warning = 'El respaldo se creó, pero no se pudo registrar la operación en auditoría.';
        }
        return response()->json(['success' => true, 'message' => 'Respaldo creado y verificado.', 'backup' => $backup, 'warning' => $warning], 201);
    }

    public function download(string $filename, BackupService $backups): BinaryFileResponse
    {
        try {
            $path = $backups->downloadPath($filename);
        } catch (Throwable) {
            abort(404, 'El respaldo no está disponible o no superó la verificación de integridad.');
        }
        return response()->download($path, $filename, ['Content-Type' => 'application/zip', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
