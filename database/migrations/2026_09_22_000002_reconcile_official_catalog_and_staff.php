<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A fresh installation has no legacy rows at this point; its official data is created by DatabaseSeeder.
        if (! Schema::hasTable('categorias_activo') || DB::table('categorias_activo')->count() === 0) {
            return;
        }

        DB::transaction(function (): void {
            $retiredIds = DB::table('activos')
                ->whereIn('categoria_id', [3, 8, 9])
                ->pluck('id')
                ->all();

            $retiredClimateIds = DB::table('activos')
                ->where('categoria_id', 7)
                ->orderByDesc('id')
                ->limit(4)
                ->pluck('id')
                ->all();

            $retiredIds = array_values(array_unique([...$retiredIds, ...$retiredClimateIds]));
            if ($retiredIds !== []) {
                $retiredAssets = DB::table('activos')->whereIn('id', $retiredIds)->orderBy('id')->get();
                $serials = $retiredAssets->pluck('serial')->implode(', ');
                $affectedReports = DB::table('reporte_activo')->whereIn('activo_id', $retiredIds)->pluck('reporte_id')->unique();
                $affectedMaintenances = DB::table('mantenimientos')->whereIn('activo_id', $retiredIds)->orderBy('id')->get();

                DB::table('auditorias')->insert([
                    'fecha_auditoria' => now(),
                    'descripcion' => 'Conciliación con el Anexo A oficial: se retiraron del catálogo operativo los activos fuera de alcance (' . $serials . '). El respaldo previo de la SQLite conserva los registros originales.',
                ]);

                foreach ($affectedReports as $reportId) {
                    $report = DB::table('reportes')->where('id', $reportId)->first(['historial']);
                    $history = trim((string) ($report?->historial ?? ''));
                    $note = '[Conciliación de catálogo 2026-09-22: el activo asociado quedó fuera del alcance oficial; se conserva este reporte sin activo operativo vinculado.]';
                    DB::table('reportes')->where('id', $reportId)->update([
                        'historial' => trim($history . "\n" . $note),
                        'updated_at' => now(),
                    ]);
                }

                foreach ($affectedMaintenances as $maintenance) {
                    DB::table('auditorias')->insert([
                        'fecha_auditoria' => now(),
                        'descripcion' => "Mantenimiento histórico {$maintenance->id} del activo {$maintenance->activo_id} retirado del catálogo oficial: {$maintenance->descripcion}",
                    ]);
                }

                // The report and maintenance entities remain, while their links to out-of-scope assets are archived in audit text.
                DB::table('reporte_activo')->whereIn('activo_id', $retiredIds)->delete();
                DB::table('mantenimientos')->whereIn('activo_id', $retiredIds)->delete();
                DB::table('tareas_calendario')->whereIn('asset_id', $retiredIds)->update(['asset_id' => null, 'updated_at' => now()]);
                DB::table('activos')->whereIn('id', $retiredIds)->delete();
            }

            DB::table('categorias_activo')->whereIn('id', [3, 8, 9])->delete();

            $categories = [
                1 => ['Terminales', 'Computadoras.', false],
                2 => ['Equipos portátiles', 'Seis notebooks técnicos, una del técnico líder y tres de gerencia.', false],
                4 => ['Impresión', 'Equipos de impresión.', true],
                5 => ['Cartelería digital', 'Equipos de cartelería digital.', false],
                6 => ['Seguridad CCTV', 'Equipos relacionados con videovigilancia.', false],
                7 => ['Climatización IoT', 'Dispositivos de climatización con capacidad de gestión IoT.', false],
                10 => ['Protección eléctrica', 'Equipos de protección eléctrica.', false],
            ];

            foreach ($categories as $id => [$name, $description, $requiresConsumable]) {
                DB::table('categorias_activo')->where('id', $id)->update([
                    'nombre' => $name,
                    'descripcion' => $description,
                    'requiere_consumible' => $requiresConsumable,
                    'updated_at' => now(),
                ]);
            }

            $additions = [
                [1, 'SN-TER-2026-026', 'ACT-TER-026', 'Terminal oficial 26'],
                [1, 'SN-TER-2026-027', 'ACT-TER-027', 'Terminal oficial 27'],
                [1, 'SN-TER-2026-028', 'ACT-TER-028', 'Terminal oficial 28'],
                [1, 'SN-TER-2026-029', 'ACT-TER-029', 'Terminal oficial 29'],
                [1, 'SN-TER-2026-030', 'ACT-TER-030', 'Terminal oficial 30'],
                [2, 'SN-NBK-2026-009', 'ACT-NBK-009', 'Notebook técnico oficial 05'],
                [2, 'SN-NBK-2026-010', 'ACT-NBK-010', 'Notebook técnico oficial 06'],
            ];

            foreach ($additions as [$categoryId, $serial, $code, $name]) {
                if (DB::table('activos')->where('serial', $serial)->exists()) {
                    continue;
                }
                $assetId = DB::table('activos')->insertGetId([
                    'categoria_id' => $categoryId,
                    'serial' => $serial,
                    'descripcion' => "Código legado: {$code}. Nombre legado: {$name}. Responsable legado: Sin asignar. Incorporado para completar el Anexo A oficial.",
                    'estado_activo' => 'Operativo',
                    'fecha_alta' => '2026-09-22',
                    'frecuencia_service' => $categoryId === 2 ? 180 : 90,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                DB::table('activo_ubicaciones')->insert([
                    'activo_id' => $assetId,
                    'piso' => 'Pendiente de asignación',
                    'sala' => 'Catálogo oficial',
                ]);
            }

            $chiefId = DB::table('jefes_tecnicos')->value('id');
            foreach ([
                ['tecnico.software01@preventiti.local', 'Técnico', 'Oficial de Software 01', 'Software'],
                ['tecnico.redes01@preventiti.local', 'Técnico', 'Oficial de Redes 01', 'Redes/Infraestructura'],
            ] as [$email, $name, $lastName, $type]) {
                $user = DB::table('usuarios')->where('email', $email)->first(['id']);
                $userId = $user?->id ?? DB::table('usuarios')->insertGetId([
                    'email' => $email,
                    'rol' => 'Empleado',
                    'password' => Hash::make(\App\Support\AccountPassword::generate($email)),
                    'activo' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('usuario_nombres')->updateOrInsert(
                    ['usuario_id' => $userId],
                    ['nombre' => $name, 'apellido' => $lastName]
                );
                DB::table('usuario_direcciones')->updateOrInsert(
                    ['usuario_id' => $userId],
                    ['calle' => 'Soporte Técnico', 'numero_calle' => 'S/N']
                );
                DB::table('tecnicos')->updateOrInsert(['id' => $userId], ['tipo_tecnico' => $type]);
                if ($chiefId) {
                    DB::table('tecnico_jefe_tecnico')->updateOrInsert(
                        ['tecnico_id' => $userId],
                        ['jefe_id' => $chiefId, 'reporte_id' => null, 'fecha_asignacion' => '2026-09-22', 'fecha_finalizacion' => null]
                    );
                }
            }
        });
    }

    public function down(): void
    {
        throw new RuntimeException('La conciliación del catálogo oficial requiere restaurar el respaldo previo para revertirse de forma segura.');
    }
};
