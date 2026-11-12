<?php

namespace Tests\Feature;

use App\Models\{AssetConsumable, Audit, CalendarTask, Report, User};
use App\Services\{OperationalSimulation, ReportService, SimulationSnapshot};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OperationalSimulationTest extends TestCase
{
    use RefreshDatabase;

    private function legacy(): array
    {
        $snapshot = app(SimulationSnapshot::class);
        $manifest = [];
        foreach (['reportes', 'alertas', 'mantenimientos', 'consumibles', 'tareas_calendario'] as $table) {
            foreach (DB::table($table)->pluck('id') as $id) $manifest[] = $snapshot->record($table, $id);
        }
        return $manifest;
    }

    private function regenerate(array $legacy = []): array
    {
        return app(OperationalSimulation::class)->refresh(CarbonImmutable::parse('2026-10-22', 'America/Montevideo'), $legacy);
    }

    public function test_new_simulation_is_coherent_and_preserves_official_catalog_accounts_and_audits(): void
    {
        $this->seed();
        DB::table('activos')->update(['fecha_alta' => '2026-09-22']);
        $protected = app(SimulationSnapshot::class)->protectedFingerprint();
        $auditRows = DB::table('auditorias')->orderBy('id')->get();
        $oldReports = Report::pluck('id');
        $result = $this->regenerate($this->legacy());
        $this->assertSame($protected, app(SimulationSnapshot::class)->protectedFingerprint());
        $this->assertEquals($auditRows, DB::table('auditorias')->whereIn('id', $auditRows->pluck('id'))->orderBy('id')->get());
        $this->assertSame(0, Report::whereIn('id', $oldReports)->count());
        $this->assertSame(36, $result['created']['reportes']);
        $this->assertSame(24, Report::where('estado_reporte', 'Finalizado')->count());
        $this->assertSame(7, Report::where('estado_reporte', 'En curso')->count());
        $this->assertSame(2, Report::where('estado_reporte', 'Diagnóstico')->count());
        $this->assertSame(3, Report::where('estado_reporte', 'Recibido/Derivado')->count());
        $this->assertSame(16, AssetConsumable::count());
        $this->assertSame(3, AssetConsumable::where('nivel', '<=', 20)->count());
        $this->assertSame(0, DB::table('tecnico_jefe_tecnico')->where('tecnico_id', 4)->count());
        $this->assertSame(2, DB::table('tecnico_jefe_tecnico')->whereNotNull('motivo_reasignacion')->count());
        foreach ([5, 6, 7, 8, 39, 40] as $id) {
            $this->assertSame(4, DB::table('tecnico_jefe_tecnico')->join('reportes', 'reportes.id', '=', 'reporte_id')->where('tecnico_id', $id)->whereColumn('fecha_finalizacion', 'fecha_cierre')->whereNull('motivo_reasignacion')->where('estado_reporte', 'Finalizado')->count());
        }
        foreach (Report::where('estado_reporte', 'Finalizado')->get() as $report) {
            $this->assertNotEmpty($report->informe_accionar);
            $this->assertNotEmpty($report->diagnostico_final);
            $this->assertTrue($report->fecha_cierre->greaterThan($report->fecha_creacion));
            $this->assertDatabaseHas('mantenimientos', ['reporte_id' => $report->id]);
        }
        $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
        $this->assertSame(0, DB::table('reportes')->join('reporte_activo', 'reportes.id', '=', 'reporte_id')->join('activos', 'activos.id', '=', 'activo_id')->whereRaw('date(fecha_creacion) < date(fecha_alta)')->count());
        $this->assertSame(0, DB::table('mantenimientos')->join('activos', 'activos.id', '=', 'activo_id')->whereRaw('date(fecha_mantenimiento) < date(fecha_alta)')->count());
        $this->assertGreaterThan(0, CalendarTask::whereDate('fecha', '2026-10-22')->count());
        $this->assertSame(0, CalendarTask::where('completed', true)->whereDate('fecha', '>', '2026-10-22')->count());
    }

    public function test_repeated_refresh_replaces_only_owned_unchanged_records_and_aborts_on_user_edits(): void
    {
        $this->seed();
        $this->regenerate($this->legacy());
        $this->regenerate();
        $this->assertSame(36, Report::count());
        $this->assertSame(16, AssetConsumable::count());
        $this->assertSame(1, DB::table('simulation_runs')->whereNull('retired_at')->count());
        $report = Report::firstOrFail();
        $report->update(['descripcion' => 'Información añadida por el usuario; conservar.']);
        $before = Report::orderBy('id')->get()->toJson();
        try {
            $this->regenerate();
            $this->fail('Debe rechazar el reemplazo de datos modificados.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('cambió', $error->getMessage());
        }
        $this->assertSame($before, Report::orderBy('id')->get()->toJson());
        $this->assertSame(2, DB::table('simulation_runs')->count());
    }

    public function test_unowned_operational_records_and_an_existing_budget_are_preserved(): void
    {
        $this->seed();
        $legacy = $this->legacy();
        $report = app(ReportService::class)->createReport(2, User::findOrFail(9), 'Media', 'Registro operativo ajeno a la simulación.');
        $consumable = AssetConsumable::create(['activo_id' => 85, 'nombre' => 'Registro conservado', 'tipo' => 'Prueba oficial', 'nivel' => 55]);
        DB::table('maintenance_budgets')->insert(['year' => 2026, 'currency' => 'UYU', 'amount_minor' => 7777700, 'scope' => 'Presupuesto oficial que debe conservarse.']);
        $snapshots = [app(SimulationSnapshot::class)->record('reportes', $report->id), app(SimulationSnapshot::class)->record('consumibles', $consumable->id)];
        $this->regenerate($legacy);
        app(SimulationSnapshot::class)->assertUnchanged($snapshots);
        $this->assertSame(37, Report::count());
        $this->assertSame(7777700, DB::table('maintenance_budgets')->value('amount_minor'));
    }

    public function test_api_cost_totals_and_alert_report_links_match_persisted_records(): void
    {
        $this->seed();
        $this->regenerate($this->legacy());
        $this->travelTo(CarbonImmutable::parse('2026-10-22 12:00:00', 'America/Montevideo'));
        $this->actingAs(User::findOrFail(4));
        $stats = $this->getJson('/api/estadisticas')->assertOk()->json('data');
        $spent = DB::table('maintenance_costs')->sum(DB::raw('quantity * unit_cost_minor')) / 100;
        $this->assertEquals($spent, $stats['budget']['executed_to_date']);
        $this->assertEquals(120000 - $spent, $stats['budget']['remaining_budget']);
        $this->assertSame('UYU', $stats['budget']['currency']);
        $this->assertEquals($spent, array_sum(array_column($stats['budget']['breakdown'], 'amount')));
        $parts = DB::table('maintenance_costs')->whereIn('kind', ['part', 'supply'])->sum(DB::raw('quantity * unit_cost_minor')) / 100;
        $this->assertEquals($parts, array_sum(array_column($stats['components_replaced'], 'total')));
        $this->assertEquals(6, collect($stats['components_replaced'])->firstWhere('name', 'Patch cord Cat6 2 m')['count']);
        $this->assertCount(6, $stats['technicians_ranking']);
        $this->assertCount(6, $stats['maintenance_months']);
        foreach ($this->getJson('/api/notificaciones')->assertOk()->json('data') as $alert) {
            $this->assertSame(DB::table('alertas')->where('id', $alert['id'])->value('reporte_id'), $alert['report_id']);
        }
        $this->getJson('/api/calendario?date=2026-10-22')->assertOk();
        $this->getJson('/api/consumibles')->assertOk();
        $this->getJson('/api/floorplan')->assertOk();
        $this->actingAs(User::findOrFail(9))->getJson('/api/estadisticas')->assertForbidden();
    }
}
