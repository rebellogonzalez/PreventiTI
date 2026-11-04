<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Snapshot first: the source schema is rebuilt so the final structure has no legacy columns.
        $read = static fn (string $table): array => Schema::hasTable($table)
            ? DB::table($table)->orderBy('id')->get()->map(static fn (object $row): array => (array) $row)->all()
            : [];

        $roles = $read('roles');
        $users = $read('usuarios');
        $categories = $read('categorias_activo');
        $locations = $read('ubicaciones');
        $assets = $read('activos');
        $reports = $read('reportes');
        $maintenances = $read('mantenimientos');
        $consumables = $read('consumibles_activo');
        $notifications = $read('notificaciones');
        $calendarTasks = $read('tareas_calendario');
        $auditLogs = $read('auditoria_logs');

        $rolesById = collect($roles)->keyBy('id');
        $usersById = collect($users)->keyBy('id');
        $locationsById = collect($locations)->keyBy('id');
        $chiefIds = [];
        $technicianIds = [];

        Schema::disableForeignKeyConstraints();
        foreach ([
            'notificaciones', 'tareas_calendario', 'mantenimientos', 'reportes',
            'consumibles_activo', 'auditoria_logs', 'activos', 'ubicaciones',
            'categorias_activo', 'usuarios', 'roles',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('usuarios', function (Blueprint $table) {
            $table->id();
            $table->string('cedula', 30)->nullable()->unique();
            $table->string('email', 150)->unique();
            $table->enum('rol', ['Administrador', 'Empleado']);
            $table->string('numero_telefono', 30)->nullable();
            // Campos técnicos para autenticación de Laravel; no representan atributos de negocio del MER.
            $table->string('password');
            $table->boolean('activo')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('usuario_nombres', function (Blueprint $table) {
            $table->unsignedBigInteger('usuario_id')->primary();
            $table->string('nombre', 100);
            $table->string('apellido', 100)->nullable();
            $table->foreign('usuario_id')->references('id')->on('usuarios')->cascadeOnDelete();
        });

        Schema::create('usuario_direcciones', function (Blueprint $table) {
            $table->unsignedBigInteger('usuario_id')->primary();
            $table->string('calle', 150)->nullable();
            $table->string('numero_calle', 20)->nullable();
            $table->foreign('usuario_id')->references('id')->on('usuarios')->cascadeOnDelete();
        });

        Schema::create('tecnicos', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->enum('tipo_tecnico', ['Hardware', 'Software', 'Redes/Infraestructura']);
        });

        Schema::create('jefes_tecnicos', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('nombre_completo', 200);
        });

        Schema::create('jefe_nombres', function (Blueprint $table) {
            $table->unsignedBigInteger('jefe_id')->primary();
            $table->string('nombre', 100);
            $table->string('apellido', 100)->nullable();
            $table->foreign('jefe_id')->references('id')->on('jefes_tecnicos')->cascadeOnDelete();
        });

        Schema::create('categorias_activo', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100)->unique();
            $table->text('descripcion')->nullable();
            $table->boolean('requiere_consumible')->default(false);
            $table->timestamps();
        });

        Schema::create('activos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('categoria_id')->constrained('categorias_activo')->restrictOnDelete();
            $table->string('serial', 100)->unique();
            $table->text('descripcion');
            $table->string('estado_activo', 50);
            $table->date('fecha_alta');
            $table->unsignedInteger('frecuencia_service');
            $table->timestamps();
            $table->index(['categoria_id', 'estado_activo'], 'idx_activos_categoria_estado');
        });

        Schema::create('activo_ubicaciones', function (Blueprint $table) {
            $table->unsignedBigInteger('activo_id')->primary();
            $table->string('piso', 100)->nullable();
            $table->string('sala', 150)->nullable();
            $table->foreign('activo_id')->references('id')->on('activos')->cascadeOnDelete();
        });

        Schema::create('auditorias', function (Blueprint $table) {
            $table->id();
            $table->dateTime('fecha_auditoria');
            $table->text('descripcion');
        });

        Schema::create('reportes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jefe_id')->nullable()->constrained('jefes_tecnicos')->nullOnDelete();
            $table->foreignId('auditoria_id')->nullable()->unique()->constrained('auditorias')->nullOnDelete();
            $table->text('historial')->nullable();
            $table->string('estado_reporte', 50)->default('Abierto');
            $table->enum('urgencia', ['Critico', 'Alto', 'Medio', 'Bajo'])->default('Medio');
            $table->text('descripcion');
            $table->dateTime('fecha_creacion');
            $table->dateTime('fecha_cierre')->nullable();
            $table->text('resolucion')->nullable();
            $table->timestamps();
            $table->index(['estado_reporte', 'urgencia'], 'idx_reportes_estado_urgencia');
        });

        Schema::create('usuario_reporte', function (Blueprint $table) {
            $table->unsignedBigInteger('usuario_id');
            $table->unsignedBigInteger('reporte_id');
            $table->primary(['usuario_id', 'reporte_id']);
            $table->foreign('usuario_id')->references('id')->on('usuarios')->cascadeOnDelete();
            $table->foreign('reporte_id')->references('id')->on('reportes')->cascadeOnDelete();
        });

        Schema::create('reporte_activo', function (Blueprint $table) {
            $table->unsignedBigInteger('reporte_id');
            $table->unsignedBigInteger('activo_id');
            $table->primary(['reporte_id', 'activo_id']);
            $table->foreign('reporte_id')->references('id')->on('reportes')->cascadeOnDelete();
            $table->foreign('activo_id')->references('id')->on('activos')->restrictOnDelete();
        });

        Schema::create('alertas', function (Blueprint $table) {
            $table->id();
            $table->string('nivel_alerta', 50);
            $table->string('tipo_alerta', 100);
            $table->dateTime('fecha_generacion');
            $table->string('estado_alerta', 50)->default('Pendiente');
            $table->enum('urgencia', ['Critico', 'Alto', 'Medio', 'Bajo'])->default('Medio');
            $table->timestamps();
        });

        Schema::create('activo_alerta', function (Blueprint $table) {
            $table->unsignedBigInteger('activo_id');
            $table->unsignedBigInteger('alerta_id');
            $table->primary(['activo_id', 'alerta_id']);
            $table->foreign('activo_id')->references('id')->on('activos')->cascadeOnDelete();
            $table->foreign('alerta_id')->references('id')->on('alertas')->cascadeOnDelete();
        });

        Schema::create('mantenimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activo_id')->constrained('activos')->restrictOnDelete();
            $table->foreignId('reporte_id')->nullable()->constrained('reportes')->nullOnDelete();
            $table->date('fecha_mantenimiento');
            $table->text('descripcion');
            $table->text('observaciones')->nullable();
            $table->timestamps();
            $table->index(['activo_id', 'fecha_mantenimiento'], 'idx_mantenimientos_activo_fecha');
        });

        Schema::create('consumibles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activo_id')->constrained('activos')->cascadeOnDelete();
            $table->string('nombre', 150);
            $table->string('tipo', 100);
            $table->decimal('nivel', 5, 2)->default(100);
            $table->timestamps();
            $table->index(['activo_id', 'nivel'], 'idx_consumibles_activo_nivel');
        });

        Schema::create('tecnico_jefe_tecnico', function (Blueprint $table) {
            $table->unsignedBigInteger('tecnico_id')->primary();
            $table->unsignedBigInteger('jefe_id');
            $table->unsignedBigInteger('reporte_id')->nullable();
            $table->date('fecha_asignacion');
            $table->date('fecha_finalizacion')->nullable();
            $table->foreign('tecnico_id')->references('id')->on('tecnicos')->cascadeOnDelete();
            $table->foreign('jefe_id')->references('id')->on('jefes_tecnicos')->restrictOnDelete();
            $table->foreign('reporte_id')->references('id')->on('reportes')->nullOnDelete();
        });

        // Calendar tasks are application support, outside the Hito's domain MER, and retain their data.
        Schema::create('tareas_calendario', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->string('hora', 10)->default('09:00');
            $table->string('titulo', 200);
            $table->string('tipo', 50)->default('mantenimiento');
            $table->string('category', 100)->default('Mantenimiento');
            $table->foreignId('technician_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->string('technician_name', 150)->nullable();
            $table->foreignId('asset_id')->nullable()->constrained('activos')->nullOnDelete();
            $table->string('asset_code', 50)->nullable();
            $table->string('priority', 20)->default('Media');
            $table->boolean('completed')->default(false);
            $table->timestamps();
            $table->index(['fecha', 'completed'], 'idx_calendario_fecha');
        });

        foreach ($users as $user) {
            $legacyRole = $rolesById->get($user['rol_id'] ?? null)['nombre'] ?? 'empleado';
            DB::table('usuarios')->insert([
                'id' => $user['id'],
                'cedula' => null,
                'email' => $user['email'],
                'rol' => $legacyRole === 'administrador' ? 'Administrador' : 'Empleado',
                'numero_telefono' => null,
                'password' => $user['password'],
                'activo' => $user['activo'] ?? true,
                'remember_token' => $user['remember_token'] ?? null,
                'created_at' => $user['created_at'] ?? now(),
                'updated_at' => $user['updated_at'] ?? now(),
            ]);

            [$name, $lastName] = array_pad(preg_split('/\s+/', trim($user['nombre'] ?? ''), 2), 2, null);
            DB::table('usuario_nombres')->insert(['usuario_id' => $user['id'], 'nombre' => $name ?: 'Sin nombre', 'apellido' => $lastName]);
            DB::table('usuario_direcciones')->insert([
                'usuario_id' => $user['id'],
                'calle' => $user['sector'] ?? null,
                'numero_calle' => ($user['sector'] ?? null) ? 'S/N' : null,
            ]);

            $isChief = (bool) ($user['is_leader'] ?? false) || $legacyRole === 'tecnico_lider';
            $isTechnician = $isChief || $legacyRole === 'tecnico';
            if ($isTechnician) {
                $specialty = mb_strtolower($user['especialidad'] ?? '');
                $type = str_contains($specialty, 'red') || str_contains($specialty, 'infra')
                    ? 'Redes/Infraestructura'
                    : (str_contains($specialty, 'software') ? 'Software' : 'Hardware');
                DB::table('tecnicos')->insert(['id' => $user['id'], 'tipo_tecnico' => $type]);
                $technicianIds[] = $user['id'];
            }
            if ($isChief) {
                DB::table('jefes_tecnicos')->insert(['id' => $user['id'], 'nombre_completo' => $user['nombre']]);
                DB::table('jefe_nombres')->insert(['jefe_id' => $user['id'], 'nombre' => $name ?: 'Sin nombre', 'apellido' => $lastName]);
                $chiefIds[] = $user['id'];
            }
        }

        foreach ($categories as $category) {
            $metadata = array_filter([
                isset($category['codigo']) ? 'Código legado: '.$category['codigo'] : null,
                isset($category['icon']) ? 'Icono legado: '.$category['icon'] : null,
            ]);
            DB::table('categorias_activo')->insert([
                'id' => $category['id'],
                'nombre' => $category['nombre'],
                'descripcion' => $metadata ? implode('. ', $metadata).'.' : null,
                'requiere_consumible' => $category['requiere_consumible'] ?? false,
                'created_at' => $category['created_at'] ?? now(),
                'updated_at' => $category['updated_at'] ?? now(),
            ]);
        }

        foreach ($assets as $asset) {
            $legacyDescription = sprintf(
                'Código legado: %s. Nombre legado: %s. Responsable legado: %s.',
                $asset['codigo'] ?? 'N/D',
                $asset['nombre'] ?? 'N/D',
                $asset['responsable'] ?? 'N/D',
            );
            DB::table('activos')->insert([
                'id' => $asset['id'],
                'categoria_id' => $asset['categoria_id'],
                'serial' => $asset['serie'],
                'descripcion' => $legacyDescription,
                'estado_activo' => ($asset['activo'] ?? true) ? ($asset['estado'] ?? 'Operativo') : 'Fuera de Servicio',
                'fecha_alta' => substr((string) ($asset['created_at'] ?? now()), 0, 10),
                'frecuencia_service' => $asset['periodicidad_dias'] ?? 90,
                'created_at' => $asset['created_at'] ?? now(),
                'updated_at' => $asset['updated_at'] ?? now(),
            ]);

            $location = $locationsById->get($asset['ubicacion_id'] ?? null);
            DB::table('activo_ubicaciones')->insert([
                'activo_id' => $asset['id'],
                'piso' => $location['nombre'] ?? null,
                'sala' => $location['sector'] ?? null,
            ]);
        }

        $reportAuditIds = [];
        foreach ($reports as $report) {
            $legacyHistory = $report['historial_operativo'] ?? null;
            $auditId = DB::table('auditorias')->insertGetId([
                'fecha_auditoria' => $report['created_at'] ?? now(),
                'descripcion' => 'Migración del reporte legado '.($report['codigo'] ?? '#'.$report['id']).'. '.$legacyHistory,
            ]);
            $reportAuditIds[$report['id']] = $auditId;
            $urgency = match ($report['prioridad'] ?? 'Media') {
                'Critica', 'Crítica' => 'Critico',
                'Alta' => 'Alto',
                'Baja' => 'Bajo',
                default => 'Medio',
            };
            DB::table('reportes')->insert([
                'id' => $report['id'],
                'jefe_id' => $chiefIds[0] ?? null,
                'auditoria_id' => $auditId,
                'historial' => $legacyHistory,
                'estado_reporte' => $report['estado'] ?? 'Abierto',
                'urgencia' => $urgency,
                'descripcion' => $report['descripcion'],
                'fecha_creacion' => $report['created_at'] ?? now(),
                'fecha_cierre' => $report['fecha_cierre'] ?? null,
                'resolucion' => $report['solucion'] ?? null,
                'created_at' => $report['created_at'] ?? now(),
                'updated_at' => $report['updated_at'] ?? now(),
            ]);
            if (! empty($report['creador_id'])) {
                DB::table('usuario_reporte')->insert(['usuario_id' => $report['creador_id'], 'reporte_id' => $report['id']]);
            }
            if (! empty($report['activo_id'])) {
                DB::table('reporte_activo')->insert(['reporte_id' => $report['id'], 'activo_id' => $report['activo_id']]);
                $alertId = DB::table('alertas')->insertGetId([
                    'nivel_alerta' => $urgency,
                    'tipo_alerta' => 'Reporte',
                    'fecha_generacion' => $report['created_at'] ?? now(),
                    'estado_alerta' => 'Pendiente',
                    'urgencia' => $urgency,
                    'created_at' => $report['created_at'] ?? now(),
                    'updated_at' => $report['updated_at'] ?? now(),
                ]);
                DB::table('activo_alerta')->insert(['activo_id' => $report['activo_id'], 'alerta_id' => $alertId]);
            }
        }

        foreach ($maintenances as $maintenance) {
            $technicianName = ! empty($maintenance['tecnico_id']) ? ($usersById->get($maintenance['tecnico_id'])['nombre'] ?? 'No identificado') : 'No identificado';
            DB::table('mantenimientos')->insert([
                'id' => $maintenance['id'],
                'activo_id' => $maintenance['activo_id'],
                'reporte_id' => null,
                'fecha_mantenimiento' => $maintenance['fecha_mantenimiento'],
                'descripcion' => $maintenance['detalle'],
                'observaciones' => 'Técnico legado: '.$technicianName.'. Próxima fecha histórica: '.($maintenance['proxima_fecha'] ?? 'N/D').'.',
                'created_at' => $maintenance['created_at'] ?? now(),
                'updated_at' => $maintenance['updated_at'] ?? now(),
            ]);
        }

        foreach ($consumables as $consumable) {
            $asset = collect($assets)->firstWhere('id', $consumable['activo_id']);
            DB::table('consumibles')->insert([
                'id' => $consumable['id'],
                'activo_id' => $consumable['activo_id'],
                'nombre' => 'Consumible de '.($asset['codigo'] ?? 'activo '.$consumable['activo_id']),
                'tipo' => $consumable['tipo'],
                'nivel' => min(100, max(0, (float) ($consumable['nivel_actual'] ?? 100))),
                'created_at' => $consumable['created_at'] ?? now(),
                'updated_at' => $consumable['updated_at'] ?? now(),
            ]);
        }

        foreach ($technicianIds as $technicianId) {
            if ($chiefIds) {
                DB::table('tecnico_jefe_tecnico')->insert([
                    'tecnico_id' => $technicianId,
                    'jefe_id' => $chiefIds[0],
                    'reporte_id' => null,
                    'fecha_asignacion' => now()->toDateString(),
                    'fecha_finalizacion' => null,
                ]);
            }
        }

        foreach ($calendarTasks as $task) {
            DB::table('tareas_calendario')->insert([
                'id' => $task['id'], 'fecha' => $task['fecha'], 'hora' => $task['hora'], 'titulo' => $task['titulo'],
                'tipo' => $task['tipo'], 'category' => $task['category'], 'technician_id' => $task['technician_id'],
                'technician_name' => $task['technician_name'], 'asset_id' => $task['asset_id'], 'asset_code' => $task['asset_code'],
                'priority' => $task['priority'], 'completed' => $task['completed'],
                'created_at' => $task['created_at'] ?? now(), 'updated_at' => $task['updated_at'] ?? now(),
            ]);
        }

        foreach ($auditLogs as $log) {
            DB::table('auditorias')->insert([
                'fecha_auditoria' => $log['created_at'] ?? now(),
                'descripcion' => sprintf('Registro legado [%s] %s: %s. %s', $log['accion'], $log['entidad'], $log['usuario_nombre'], $log['detalle']),
            ]);
        }
        foreach ($notifications as $notification) {
            DB::table('auditorias')->insert([
                'fecha_auditoria' => $notification['created_at'] ?? now(),
                'descripcion' => sprintf('Notificación histórica del reporte %s al usuario %s: %s — %s. Leída: %s', $notification['reporte_id'], $notification['destinatario_id'], $notification['titulo'], $notification['mensaje'], $notification['leida_at'] ?? 'No'),
            ]);
        }

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        throw new \RuntimeException('La migración al Hito 1 conserva datos mediante transformación y no tiene reversión automática segura. Restaure un respaldo previo para volver al modelo anterior.');
    }
};
