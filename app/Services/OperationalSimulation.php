<?php

namespace App\Services;

use App\Models\{Alert, Asset, AssetConsumable, Audit, CalendarTask, Maintenance, Report, TechnicalChief, Technician, User};
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\{DB, Date};
use RuntimeException;

class OperationalSimulation
{
    public function __construct(private readonly SimulationSnapshot $snapshot, private readonly ReportService $reports, private readonly MaintenanceService $maintenance) {}

    public function refresh(CarbonImmutable $reference, array $legacy = []): array
    {
        return DB::transaction(function () use ($reference, $legacy) {
            $protected = $this->snapshot->protectedFingerprint();
            $previous = DB::table('simulation_runs')->whereNull('retired_at')->get();
            $owned = $legacy;
            foreach ($previous as $run) $owned = [...$owned, ...json_decode($run->manifest, true, 512, JSON_THROW_ON_ERROR)];
            $this->snapshot->deleteOwned($owned);
            DB::table('simulation_runs')->whereIn('id', $previous->pluck('id'))->update(['retired_at' => now()]);
            $before = $this->snapshot->ids();
            $runId = DB::table('simulation_runs')->insertGetId(['reference_date' => $reference->toDateString(), 'manifest' => '[]', 'created_at' => now()]);
            $this->generate($reference, $runId);
            if ($protected !== $this->snapshot->protectedFingerprint()) throw new RuntimeException('La simulación intentó alterar datos oficiales. Operación revertida.');
            $manifest = $this->snapshot->addedSince($before);
            DB::table('simulation_runs')->where('id', $runId)->update(['manifest' => json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
            return ['run_id' => $runId, 'reference_date' => $reference->toDateString(), 'created' => collect($manifest)->countBy('table')->all(), 'removed' => collect($owned)->countBy('table')->all()];
        });
    }

    private function generate(CarbonImmutable $day, int $runId): void
    {
        $chief = User::findOrFail(TechnicalChief::firstActiveId());
        $scenarioTechnicians = [5, 6, 7, 8, 39, 40];
        $technicians = User::with('nombres')->whereKey($scenarioTechnicians)->whereIn('id', Technician::select('id'))
            ->whereNotIn('id', TechnicalChief::select('id'))->where('activo', true)->orderBy('id')->get()->keyBy('id');
        foreach ($scenarioTechnicians as $id) if (! $technicians->has($id)) throw new RuntimeException("Falta el técnico activo {$id}; revisar el catálogo antes de simular.");
        $creators = User::with('nombres')->where('activo', true)->where('rol', 'Empleado')->whereNotIn('id', Technician::pluck('id'))->orderBy('id')->get();
        if ($creators->isEmpty()) throw new RuntimeException('No hay empleados activos para los casos de prueba.');
        $employeeAssetIds = Asset::inReportAreasFor($creators->first())->pluck('id')->flip();
        $reporterFor = fn (Asset $asset, int $index, User $technician) => $employeeAssetIds->has($asset->id)
            ? $creators[$index % $creators->count()]
            : $technician;
        $assets = Asset::with('categoria')->orderBy('id')->get()->keyBy('id');
        $marker = "Datos de prueba persistentes. Simulación #{$runId}; referencia {$day->toDateString()}.";

        // Baseline preventive work uses the real category and configured service interval.
        foreach ($assets->values() as $index => $asset) {
            if (! $asset->activo || $asset->mantenimientos()->exists()) continue;
            $technicianId = match ($asset->categoria_id) { 6 => 8, 7, 10 => 7, 2, 5 => 39, default => 5 };
            $dueIn = [1 => -3, 2 => 3, 53 => 5, 77 => -1][$asset->id] ?? 20 + ($index * 7 % 40);
            $date = $day->subDays(max(1, $asset->frecuencia_service - $dueIn))->setTime(9 + $index % 7, 0);
            $date = $date->max(CarbonImmutable::parse($asset->fecha_alta)->setTime(9, 0));
            $detail = match ($asset->categoria_id) {
                1, 2 => 'Limpieza preventiva, revisión de ventilación, salud SMART y actualizaciones. Sin sustitución de piezas.',
                4 => 'Limpieza del recorrido de papel, prueba de alimentación y revisión de contadores. Sin cambio de tóner.',
                5 => 'Revisión de señal, programación de encendido y reproducción de contenido en pantalla.',
                6 => 'Limpieza de óptica, control de estanqueidad y verificación de imagen y alimentación PoE.',
                7 => 'Limpieza de filtros lavables, inspección de desagüe y medición de temperatura de impulsión.',
                10 => 'Inspección de batería, prueba de transferencia y autonomía con carga controlada.',
                default => 'Inspección preventiva según ficha del activo.',
            };
            $this->at($date, function () use ($asset, $technicians, $technicianId, $date, $detail, $marker) {
                $maintenance = $this->maintenance->registerMaintenance($asset->id, $technicians[$technicianId], $date->toDateString(), $detail);
                $maintenance->update(['observaciones' => $maintenance->observaciones.' '.$marker]);
                if ($asset->categoria_id === 7) $this->cost($maintenance, $asset, 'service', 'Servicio preventivo de climatización', 1, 1200, 'Limpieza técnica y revisión del drenaje; incluye mano de obra externa.');
                $task = $this->task($asset, $technicians[$technicianId], $date, 'mantenimiento', 'Preventivo realizado: '.$asset->codigo, true);
                $maintenance->update(['task_id' => $task->id]);
            });
        }

        $cases = $this->cases();
        foreach ($cases as $index => $case) {
            [$assetId, $technicianId, $description, $diagnosis, $action, $part, $price, $quantity] = $case;
            $asset = $assets->get($assetId) ?? throw new RuntimeException("Falta el activo {$assetId}.");
            $created = $day->subDays(44 - $index)->setTime(8 + $index % 4, 10)
                ->max(CarbonImmutable::parse($asset->fecha_alta->toDateString(), 'America/Montevideo')->setTime(8 + $index % 4, 10));
            if ($created->greaterThanOrEqualTo($day->startOfDay())) throw new RuntimeException('El catálogo contiene altas demasiado recientes para este historial; no se alteraron los datos.');
            $closed = $created->addHours(5 + $index % 3);
            $creator = $reporterFor($asset, $index, $technicians[$technicianId]);
            $report = $this->at($created, fn () => $this->reports->createReport($asset->id, $creator, $index % 5 === 0 ? 'Alta' : 'Media', $description));
            $report->update(['historial' => $marker]);
            // Two reassignment cases exercise the history without assigning the chief.
            $firstTechnician = $index === 4 ? 5 : ($index === 20 ? 8 : $technicianId);
            $this->at($created->addMinutes(20), fn () => $this->reports->assignTechnician($report, $chief, $firstTechnician, null));
            if ($firstTechnician !== $technicianId) $this->at($created->addHour(), fn () => $this->reports->assignTechnician($report, $chief, $technicianId, 'La inspección inicial requiere intervención de redes; se deriva al especialista disponible.'));
            $this->at($created->addHours(2), fn () => $this->reports->transition($report->fresh(), $chief, 'Diagnóstico', $action, $diagnosis));
            $this->at($closed, function () use ($report, $chief, $action, $diagnosis, $asset, $technicians, $technicianId, $closed, $marker, $part, $price, $quantity) {
                $this->reports->transition($report->fresh(), $chief, 'Finalizado', $action, $diagnosis);
                $maintenance = $this->maintenance->registerMaintenance($asset->id, $technicians[$technicianId], $closed->toDateString(), $action);
                $maintenance->update(['reporte_id' => $report->id, 'observaciones' => $maintenance->observaciones.' '.$marker]);
                if ($part) $this->cost($maintenance, $asset, str_contains($part, 'Tóner') ? 'supply' : 'part', $part, $quantity, $price, $diagnosis);
                Alert::where('reporte_id', $report->id)->update(['estado_alerta' => 'Leída']);
                $this->task($asset, $technicians[$technicianId], $closed, 'reporte', $report->codigo.' · intervención y cierre', true, $report->id);
            });
        }

        // A report enters the active queue as soon as a specialist is assigned.
        $openAssets = [12, 43, 77, 50, 31, 39];
        foreach ($technicians->values() as $index => $technician) {
            $asset = $assets[$openAssets[$index]];
            $created = $day->subDays(3)->setTime(9 + $index, 0);
            $description = [
                'El lector USB se desconecta al mover el cable del puesto.',
                'La cámara presenta pérdida intermitente de enlace de red.',
                'La UPS informa autonomía reducida durante la prueba de batería.',
                'La cámara presenta condensación interna y pérdida de nitidez.',
                'La notebook demora el inicio de sesión después de una actualización.',
                'La impresora de etiquetas pierde conexión con el puesto de trabajo.',
            ][$index];
            $report = $this->at($created, fn () => $this->reports->createReport($asset->id, $reporterFor($asset, $index, $technician), $index === 2 ? 'Critica' : 'Alta', $description));
            $report->update(['historial' => $marker]);
            $this->at($created->addMinutes(30), fn () => $this->reports->assignTechnician($report, $chief, $technician->id, null));
            if (in_array($index, [2, 3], true)) {
                $diagnosis = $index === 2 ? 'Batería VRLA degradada; requiere sustitución del conjunto compatible y prueba de autonomía.' : 'Junta de estanqueidad deteriorada; requiere recambio y secado de la cúpula.';
                $action = $index === 2 ? 'Se midió tensión y autonomía. Se aisló la batería defectuosa; pendiente de instalar repuesto.' : 'Se inspeccionó la cúpula y se verificó el ingreso de humedad; pendiente de sustitución de la junta.';
                $this->at($created->addHours(3), fn () => $this->reports->transition($report->fresh(), $chief, 'Diagnóstico', $action, $diagnosis));
            }
            Alert::where('reporte_id', $report->id)->update(['estado_alerta' => 'Leída']);
            $this->task($asset, $technician, $day->addDays($index % 3)->setTime(9 + $index, 0), 'reporte', $report->codigo.' · completar intervención', false, $report->id);

            $asset = $assets[[9, 44, 55, 37, 40, 84][$index]];
            $received = $this->at($day->subDay()->setTime(10 + $index, 15), fn () => $this->reports->createReport($asset->id, $reporterFor($asset, $index + 6, $technician), $index % 2 ? 'Media' : 'Baja', [
                'El teclado numérico registra pulsaciones duplicadas.', 'La imagen de la cámara se congela durante algunos segundos.',
                'El equipo de climatización produce vibración al iniciar.', 'La impresora muestra aviso de tóner bajo.',
                'La pantalla de cartelería no respeta el horario de encendido.', 'La notebook pierde conectividad al mover el conector Ethernet.',
            ][$index]));
            $received->update(['historial' => $marker]);
            if ($index < 3) {
                $this->at($day->subDay()->setTime(15, $index * 10), fn () => $this->reports->assignTechnician($received, $chief, $technician->id, null));
                Alert::where('reporte_id', $received->id)->update(['estado_alerta' => 'Leída']);
            }
        }

        foreach (Asset::with(['mantenimientos', 'categoria'])->get() as $asset) {
            $next = $asset->next_maintenance;
            if (! $asset->activo || ! $next || $next->greaterThan($day->addDays(60))) continue;
            $technicianId = match ($asset->categoria_id) { 6 => 8, 7, 10 => 7, 2, 5 => 39, default => 5 };
            $this->task($asset, $technicians[$technicianId], CarbonImmutable::parse($next->toDateString(), 'America/Montevideo')->setTime(9 + $asset->id % 7, 0), 'mantenimiento', 'Revisión preventiva: '.$asset->codigo);
        }

        foreach ($assets->values() as $index => $asset) {
            if ($asset->consumibles()->exists()) continue; // An unowned existing level is never overwritten.
            $levels = match ($asset->categoria_id) { 4 => [68, 16, 82, 43], 7 => [80, 72, 18, 64, 88, 76], 10 => [96, 94, 71, 66, 19, 83], default => [] };
            if (! $levels) continue;
            $position = $assets->where('categoria_id', $asset->categoria_id)->keys()->search($asset->id);
            $type = match ($asset->categoria_id) { 4 => str_contains(mb_strtolower($asset->nombre), 'térmica') ? 'Etiquetas térmicas / Ribbon' : 'Tóner / Tambor', 7 => 'Filtro de aire (vida útil estimada)', 10 => 'Batería VRLA (salud estimada)' };
            AssetConsumable::create(['activo_id' => $asset->id, 'nombre' => $type.' · '.$asset->codigo, 'tipo' => $type, 'nivel' => $levels[$position]]);
            if ($levels[$position] <= 20) $this->task($asset, $technicians[$asset->categoria_id === 4 ? 8 : 7], $day->addDays($asset->categoria_id === 10 ? 0 : 1)->setTime(16, 0), 'consumible', 'Control y reposición: '.$asset->codigo);
        }
        $this->task($assets[26], $technicians[6], $day->setTime(11, 30), 'red', 'Verificar conectividad y etiquetado de puestos');
        $this->task($assets[31], $technicians[39], $day->addDay()->setTime(15, 0), 'auditoria', 'Comprobar restauración de documentos de prueba');
        if (! DB::table('maintenance_budgets')->where('year', $day->year)->exists()) DB::table('maintenance_budgets')->insert([
            'year' => $day->year, 'currency' => 'UYU', 'amount_minor' => 12000000,
            'scope' => 'Escenario de prueba: 120.000 UYU anuales para insumos, repuestos menores y servicios externos de 69 activos. Importes estimados; excluye sueldos, compra de equipos y licencias. No representa compras oficiales.',
        ]);
        Audit::create(['fecha_auditoria' => now(), 'descripcion' => $marker.' Carga realizada mediante los servicios de reportes y mantenimiento; catálogo y cuentas conservados.']);
    }

    private function at(CarbonImmutable $date, callable $callback): mixed
    {
        $previous = Date::getTestNow();
        Date::setTestNow($date->utc());
        try { return $callback(); } finally { Date::setTestNow($previous); }
    }

    private function task(Asset $asset, User $technician, CarbonImmutable $date, string $type, string $title, bool $completed = false, ?int $reportId = null): CalendarTask
    {
        return CalendarTask::create(['reporte_id' => $reportId, 'fecha' => $date->toDateString(), 'hora' => $date->format('H:i'), 'titulo' => $title,
            'tipo' => $type, 'category' => match ($type) { 'reporte' => 'Reporte', 'consumible' => 'Consumible', 'red' => 'Red & Seguridad', 'auditoria' => 'Auditoría', default => 'Mantenimiento' },
            'technician_id' => $technician->id, 'technician_name' => $technician->nombre, 'asset_id' => $asset->id, 'asset_code' => $asset->codigo,
            'priority' => 'Media', 'completed' => $completed]);
    }

    private function cost(Maintenance $maintenance, Asset $asset, string $kind, string $name, int $quantity, int $unitPrice, string $reason): void
    {
        DB::table('maintenance_costs')->insert(['asset_id' => $asset->id, 'maintenance_id' => $maintenance->id,
            'incurred_on' => $maintenance->fecha_mantenimiento->toDateString(), 'kind' => $kind, 'name' => $name,
            'category' => $asset->categoria->nombre, 'quantity' => $quantity, 'unit_cost_minor' => $unitPrice * 100, 'currency' => 'UYU', 'reason' => $reason]);
    }

    private function cases(): array
    {
        // Asset, specialist, symptom, diagnosis, intervention, replacement, estimated UYU, units.
        $usb = ['El lector USB se desconecta al mover el cable del puesto.', 'Cable USB con conductor fatigado cerca del conector.', 'Se sustituyó el cable USB, se fijó el tendido y se verificaron 50 lecturas continuas.', 'Cable USB reforzado', 350, 1];
        $network = ['La cámara presenta pérdida intermitente de enlace de red.', 'Patch cord con pestaña dañada y falso contacto.', 'Se reemplazó el patch cord, se aseguró el tendido y se verificó transmisión continua durante 30 minutos.', 'Patch cord Cat6 2 m', 220, 1];
        $toner = ['La impresora presenta franjas y aviso de tóner bajo.', 'Cartucho agotado; rodillos y tambor verificados sin daño.', 'Se sustituyó el cartucho compatible, se limpió el recorrido y se validaron 20 páginas de prueba.', 'Tóner compatible', 1850, 1];
        $software = ['La terminal demora el inicio de sesión después de una actualización.', 'Caché de actualización inconsistente y servicios de inicio redundantes.', 'Se reparó la caché, se aplicó la actualización y se validaron tres reinicios con el usuario.', null, 0, 0];
        return [
            [12, 5, ...$usb], [9, 5, 'La terminal se reinicia al abrir varias aplicaciones.', 'Módulo DDR4 con errores reproducibles de memoria.', 'Se cambió un módulo y se completaron dos pasadas de diagnóstico sin errores.', 'Memoria DDR4 8 GB', 1100, 1],
            [14, 5, 'El arranque de la terminal presenta errores de lectura.', 'SSD con sectores no recuperables.', 'Se sustituyó la unidad y se restauró la imagen de prueba; arranque y archivos verificados.', 'SSD SATA 480 GB', 1750, 1], [36, 5, ...$toner],
            [43, 6, ...$network], [44, 6, ...$network], [1, 6, 'La terminal pierde enlace al mover el cable de red.', 'Patch cord con retención mecánica dañada.', 'Se reemplazó el patch cord y se validó el enlace y la transferencia local.', 'Patch cord Cat6 2 m', 220, 1],
            [26, 6, 'La notebook no obtiene dirección IP en la base de conexión.', 'Configuración IPv4 estática residual.', 'Se restauró DHCP y se verificó resolución de nombres y acceso a los servicios.', null, 0, 0],
            [53, 7, 'La climatización reduce el caudal de aire.', 'Filtro deteriorado que ya no retiene polvo de forma uniforme.', 'Se reemplazó el filtro compatible y se verificó caudal y temperatura.', 'Filtro de aire compatible', 650, 1],
            [54, 7, 'La climatización reduce el caudal de aire.', 'Filtro deteriorado que ya no retiene polvo de forma uniforme.', 'Se reemplazó el filtro compatible y se verificó caudal y temperatura.', 'Filtro de aire compatible', 650, 1],
            [73, 7, 'La UPS informa autonomía reducida durante la prueba de batería.', 'Conjunto de baterías VRLA agotado por uso.', 'Se sustituyó el conjunto de baterías de la UPS de 3000 VA y se verificó transferencia y autonomía con carga controlada.', 'Conjunto de baterías para UPS', 8500, 1],
            [74, 7, 'La UPS informa autonomía reducida durante la prueba de batería.', 'Conjunto de baterías VRLA agotado por uso.', 'Se sustituyó el conjunto de baterías de la UPS de 1500 VA y se verificó transferencia y autonomía con carga controlada.', 'Conjunto de baterías para UPS', 4800, 1],
            [37, 8, ...$toner], [38, 8, 'La impresora toma más de una hoja por vez.', 'Rodillo de separación desgastado.', 'Se sustituyó el rodillo y se validó alimentación de 50 hojas sin atasco.', 'Rodillo de arrastre', 850, 1],
            [45, 8, 'La cámara presenta condensación interna y pérdida de nitidez.', 'Junta de estanqueidad deteriorada.', 'Se sustituyó la junta, se secó la cúpula y se verificó imagen y cierre hermético.', 'Junta de cúpula CCTV', 280, 1], [13, 8, ...$usb],
            [3, 39, ...$software], [4, 39, ...$software], [31, 39, 'La notebook demora el inicio de sesión después de una actualización.', 'Servicios de inicio duplicados tras la actualización.', 'Se depuraron los servicios de inicio y se validaron tres accesos sin demoras.', null, 0, 0],
            [40, 39, 'La pantalla de cartelería no reproduce la lista programada.', 'Ruta de contenido modificada en el reproductor.', 'Se corrigió la ruta y se validó un ciclo completo de reproducción programada.', null, 0, 0],
            [47, 40, ...$network], [48, 40, 'La cámara presenta errores de enlace PoE.', 'Conectores RJ45 con terminación deficiente.', 'Se rehízo la terminación de ambos extremos y se comprobó continuidad y alimentación.', 'Conector RJ45 Cat6', 70, 2],
            [84, 40, 'La notebook pierde enlace al mover el conector Ethernet.', 'Patch cord con pestaña dañada.', 'Se sustituyó el patch cord y se verificó estabilidad durante una transferencia local.', 'Patch cord Cat6 2 m', 220, 1],
            [39, 40, 'La impresora de etiquetas pierde conexión con el puesto de trabajo.', 'Patch cord defectuoso en el tramo final.', 'Se sustituyó el cable de red y se validaron 30 etiquetas consecutivas.', 'Patch cord Cat6 2 m', 220, 1],
        ];
    }
}
