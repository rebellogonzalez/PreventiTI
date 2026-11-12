<?php

use App\Http\Controllers\Api\AssetController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CalendarController;
use App\Http\Controllers\Api\ConsumableController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\MaintenanceController;
use App\Http\Controllers\Api\NetworkSimulationController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\StatsController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

// =============================================================================
// RUTAS PÚBLICAS
// =============================================================================
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
});

// =============================================================================
// RUTAS PROTEGIDAS (Requieren Autenticación y Verificación de Cuenta Activa)
// =============================================================================
Route::middleware('auth.preventiti')->group(function () {

    // 1. Gestión de Sesión y Perfil
    Route::prefix('auth')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::get('/staff-revision', [AuthController::class, 'staffRevision']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::middleware('role:administrador,tecnico_lider')->prefix('users')->group(function () {
            Route::get('/', [UserController::class, 'index']);
            Route::post('/', [UserController::class, 'store']);
            Route::delete('/{id}', [UserController::class, 'destroy'])->whereNumber('id');
        });
        Route::get('/task-assignees', [AuthController::class, 'taskAssignees'])->middleware('role:administrador,tecnico_lider');
    });

    // 2. Dashboard y Monitoreo Preventivo (RF-09)
    Route::prefix('dashboard')->group(function () {
        Route::get('/stats', [DashboardController::class, 'stats']);
        Route::get('/semaforo', [DashboardController::class, 'semaforo'])->middleware('role:administrador,tecnico_lider,tecnico');
        Route::get('/today-tasks', [DashboardController::class, 'todayTasks'])->middleware('role:administrador,tecnico_lider,tecnico');
        Route::get('/critical-consumables', [DashboardController::class, 'criticalConsumables'])->middleware('role:administrador,tecnico_lider,tecnico');
        Route::get('/upcoming-maintenances', [DashboardController::class, 'upcomingMaintenances'])->middleware('role:administrador,tecnico_lider,tecnico');
        Route::get('/audit-logs', [DashboardController::class, 'auditLogs']);
    });

    // 3. Activos e Inventario (RF-02, RF-03 - Restricción por Área y Prevención IDOR)
    Route::get('/floorplan', [AssetController::class, 'floorplan']);
    Route::patch('/floorplan/assets/{id}/position', [AssetController::class, 'saveMapPosition']);
    Route::prefix('activos')->group(function () {
        Route::get('/', [AssetController::class, 'index']);
        Route::get('/categories', [AssetController::class, 'categories'])->middleware('role:administrador,tecnico_lider,tecnico');
        Route::get('/locations', [AssetController::class, 'locations'])->middleware('role:administrador,tecnico_lider,tecnico');
        Route::get('/{id}', [AssetController::class, 'show']);

        Route::middleware('role:administrador,tecnico_lider')->group(function () {
            Route::post('/', [AssetController::class, 'store']);
            Route::patch('/{id}/toggle', [AssetController::class, 'changeRetirement']);
            Route::delete('/{id}', [AssetController::class, 'changeRetirement']);
        });
        Route::patch('/{asset}', [AssetController::class, 'update'])->whereNumber('asset')->middleware('role:administrador');
    });

    // 4. Reportes de situación y alertas persistentes del Hito 1
    Route::prefix('reportes')->group(function () {
        Route::get('/', [ReportController::class, 'index']);
        Route::get('/historial', [ReportController::class, 'history']);
        Route::get('/activos-disponibles', [ReportController::class, 'availableAssets']);
        Route::middleware('role:tecnico_lider')->get('/tecnicos', [ReportController::class, 'technicians']);
        Route::get('/{id}', [ReportController::class, 'show']);
        Route::post('/', [ReportController::class, 'store']);
        Route::middleware('role:tecnico_lider')->patch('/{id}/asignacion', [ReportController::class, 'assign']);
        Route::middleware('role:tecnico_lider,tecnico')->patch('/{id}/estado', [ReportController::class, 'transition']);
    });

    Route::middleware('role:tecnico_lider')->prefix('notificaciones')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::patch('/{id}/leer', [NotificationController::class, 'markRead']);
    });

    // 5. Mantenimientos Preventivos (RF-07)
    Route::prefix('mantenimientos')->group(function () {
        Route::get('/', [MaintenanceController::class, 'index']);
        Route::patch('/{id}/fecha', [MaintenanceController::class, 'correctDate'])->whereNumber('id')->middleware('role:administrador,tecnico_lider');
        Route::middleware('role:administrador,tecnico_lider,tecnico')->group(function () {
            Route::post('/', [MaintenanceController::class, 'store']);
        });
    });

    // 6. Consumibles (RF-08)
    Route::prefix('consumibles')->group(function () {
        Route::get('/', [ConsumableController::class, 'index']);
        Route::middleware('role:administrador,tecnico_lider,tecnico')->group(function () {
            Route::patch('/{id}', [ConsumableController::class, 'update']);
        });
    });

    // 7. Estadísticas y Presupuestos (Segmentadas por Rol y Jerarquía)
    Route::middleware('role:administrador,tecnico_lider')->group(function () {
        Route::get('/estadisticas', [StatsController::class, 'index']);
    });

    // 8. Calendario de Tareas
    Route::prefix('calendario')->group(function () {
        Route::get('/', [CalendarController::class, 'index'])->middleware('role:administrador,tecnico_lider,tecnico');
        Route::middleware('role:administrador,tecnico_lider,tecnico')->group(function () {
            Route::post('/', [CalendarController::class, 'store'])->middleware('role:administrador,tecnico_lider');
            Route::patch('/{id}', [CalendarController::class, 'edit'])->whereNumber('id')->middleware('role:administrador,tecnico_lider');
            Route::patch('/{id}/asignacion', [CalendarController::class, 'assign'])->whereNumber('id')->middleware('role:administrador,tecnico_lider');
            Route::patch('/{id}/toggle', [CalendarController::class, 'toggleCompleted']);
        });
    });

    // 9. Laboratorio de Redes e Infraestructura (Técnico Líder y Administrador)
    Route::middleware('role:administrador,tecnico_lider')->prefix('infraestructura')->group(function () {
        Route::get('/status', [NetworkSimulationController::class, 'status']);
        Route::post('/scan', [NetworkSimulationController::class, 'scan'])->middleware('throttle:6,1');
        Route::post('/drp-backup', [NetworkSimulationController::class, 'drpBackup'])->middleware('throttle:3,1');
        Route::get('/backups/{filename}/download', [NetworkSimulationController::class, 'download'])
            ->where('filename', 'preventiti_[0-9]{8}_[0-9]{6}_[a-f0-9]{12}\.zip');
    });

});
