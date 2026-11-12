<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Asset;
use App\Models\Report;
use App\Models\Technician;
use App\Models\TechnicalChief;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PreventiTITest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_hito1_schema_contains_all_domain_tables_and_pivots(): void
    {
        foreach (['usuarios', 'usuario_nombres', 'usuario_direcciones', 'tecnicos', 'jefes_tecnicos', 'jefe_nombres', 'tecnico_jefe_tecnico', 'reportes', 'usuario_reporte', 'reporte_activo', 'auditorias', 'activos', 'activo_ubicaciones', 'alertas', 'activo_alerta', 'mantenimientos', 'categorias_activo', 'consumibles'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Falta la tabla {$table}");
        }
        $this->assertFalse(Schema::hasTable('roles'));
        $this->assertFalse(Schema::hasTable('notificaciones'));
        $this->assertFalse(Schema::hasTable('ubicaciones'));
        $this->assertFalse(Schema::hasTable('consumibles_activo'));
        $this->assertTrue(Schema::hasColumns('reportes', ['informe_accionar', 'diagnostico_final']));
        $this->assertTrue(Schema::hasColumns('usuarios', ['nombre_completo', 'direccion']));
        $this->assertTrue(Schema::hasColumn('activos', 'ubicacion'));
    }

    public function test_official_catalog_and_user_distribution_are_seeded(): void
    {
        $this->assertSame(69, Asset::count());
        $this->assertSame(40, User::count());
        $this->assertSame(3, User::where('rol', 'Administrador')->count());
        $this->assertSame(1, TechnicalChief::count());
        $this->assertSame(7, Technician::count()); // 1 líder técnico + 6 especialistas.
        $this->assertSame(6, User::all()->filter(fn (User $user) => $user->roleName() === 'tecnico')->count());
        $this->assertSame(30, User::all()->filter(fn (User $user) => $user->roleName() === 'empleado')->count());

        $expected = ['Terminales' => 30, 'Equipos portátiles' => 10, 'Impresión' => 4, 'Climatización IoT' => 6, 'Seguridad CCTV' => 10, 'Cartelería digital' => 3, 'Protección eléctrica' => 6];
        foreach ($expected as $category => $count) {
            $this->assertSame($count, Asset::whereHas('categoria', fn ($query) => $query->where('nombre', $category))->count(), $category);
        }
    }

    public function test_simulated_areas_are_exposed_as_eight_map_locations(): void
    {
        $leader = User::findOrFail(4);
        $this->actingAs($leader)
            ->getJson('/api/activos/locations')
            ->assertOk()
            ->assertJsonCount(8, 'data')
            ->assertJsonPath('data.4.sala', 'Oficina de gerencia y coordinación')
            ->assertJsonPath('data.4.map_label', 'Gerencia y coordinación');

        $this->actingAs($leader)
            ->getJson('/api/activos')
            ->assertOk()
            ->assertJsonFragment(['id' => 30, 'location_id' => 5]);

        $device = ['categoria_id' => 2, 'nombre' => 'Notebook de prueba', 'serie' => 'ZONA-005', 'responsable_id' => 9];
        $this->postJson('/api/activos', $device)->assertUnprocessable()->assertJsonValidationErrors('ubicacion_id');
        $id = $this->postJson('/api/activos', [...$device, 'ubicacion_id' => 5])->assertCreated()
            ->assertJsonPath('data.responsible_id', 9)->json('data.id');
        $asset = collect($this->getJson('/api/floorplan')->assertOk()->json('data.assets'))->firstWhere('id', $id);
        $this->assertSame('management', $asset['room_id']);
    }

    public function test_employee_cannot_create_asset(): void
    {
        $employee = User::findOrFail(9);
        $this->actingAs($employee)->postJson('/api/activos', ['categoria_id' => 1, 'ubicacion_id' => 1, 'nombre' => 'Terminal POS no autorizada', 'serie' => 'SN-TEST-HACK-001'])->assertStatus(403)->assertJsonFragment(['success' => false, 'user_role' => 'empleado']);
    }

    public function test_admin_toggle_changes_hito_asset_state(): void
    {
        $admin = User::findOrFail(1);
        $asset = Asset::findOrFail(1);
        $this->actingAs($admin)->patchJson("/api/activos/{$asset->id}/toggle")->assertOk();
        $this->assertDatabaseHas('activos', ['id' => $asset->id, 'estado_activo' => 'Retirado']);
    }

    public function test_consumable_level_triggers_attention_alert(): void
    {
        Carbon::setTestNow('2026-09-02 10:00:00');
        $leader = User::findOrFail(4);
        $asset = Asset::findOrFail(36);
        $this->actingAs($leader)->postJson('/api/mantenimientos', [
            'asset_id' => $asset->id, 'maintenance_date' => '2026-09-02', 'detail' => 'Inspección preventiva al día.',
        ])->assertCreated();
        $this->patchJson("/api/consumibles/{$asset->id}", ['level' => 15])->assertOk()->assertJsonPath('data.status_alert', 'atencion')->assertJsonPath('data.consumable_level', 15);
        Carbon::setTestNow();
    }

    public function test_report_creation_uses_normalized_pivots_and_creates_alert(): void
    {
        $employee = User::findOrFail(9);
        $alertsBefore = Alert::count();
        $response = $this->actingAs($employee)->postJson('/api/reportes', ['asset_id' => 1, 'priority' => 'Alta', 'description' => 'Lector de códigos no enciende tras inicio de caja.']);
        $response->assertCreated()->assertJsonPath('data.asset_id', 1)->assertJsonPath('data.creator_id', $employee->id);
        $reportId = $response->json('data.id');
        $response->assertJsonPath('data.status', 'Recibido/Derivado')->assertJsonPath('data.assigned_technician', null);
        $this->assertDatabaseHas('usuario_reporte', ['usuario_id' => $employee->id, 'reporte_id' => $reportId]);
        $this->assertDatabaseHas('reporte_activo', ['reporte_id' => $reportId, 'activo_id' => 1]);
        $this->assertSame($alertsBefore + 1, Alert::count());
        $this->assertDatabaseHas('alertas', ['tipo_alerta' => 'Reporte', 'urgencia' => 'Alto']);
        $this->assertInstanceOf(Report::class, Report::findOrFail($reportId));
        $this->actingAs(\App\Models\User::findOrFail(1))->getJson('/api/notificaciones')->assertForbidden();
        $this->actingAs(\App\Models\User::findOrFail(4))->getJson('/api/notificaciones')->assertOk();
    }

    public function test_the_technical_chief_can_assign_reassign_and_advance_a_report(): void
    {
        $employee = User::findOrFail(9);
        $chief = User::findOrFail(4);
        $firstTechnician = User::findOrFail(5);
        $secondTechnician = User::findOrFail(6);

        $reportId = $this->actingAs($employee)
            ->postJson('/api/reportes', ['asset_id' => 1, 'priority' => 'Alta', 'description' => 'La terminal no completa el proceso de inicio de sesión.'])
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($employee)
            ->patchJson("/api/reportes/{$reportId}/asignacion", ['technician_id' => $firstTechnician->id])
            ->assertForbidden();

        $this->actingAs($chief)
            ->patchJson("/api/reportes/{$reportId}/estado", ['status' => 'En curso'])
            ->assertUnprocessable();

        $this->actingAs($chief)
            ->patchJson("/api/reportes/{$reportId}/asignacion", ['technician_id' => $firstTechnician->id])
            ->assertOk()
            ->assertJsonPath('data.assigned_technician.id', $firstTechnician->id)
            ->assertJsonPath('data.status', 'En curso');

        $this->actingAs($chief)
            ->patchJson("/api/reportes/{$reportId}/asignacion", ['technician_id' => $secondTechnician->id, 'reassignment_reason' => 'Reasignación por disponibilidad del técnico inicial.'])
            ->assertOk()
            ->assertJsonPath('data.assigned_technician.id', $secondTechnician->id)
            ->assertJsonPath('data.status', 'En curso');

        $this->assertDatabaseHas('tecnico_jefe_tecnico', [
            'reporte_id' => $reportId,
            'tecnico_id' => $firstTechnician->id,
            'motivo_reasignacion' => 'Reasignación por disponibilidad del técnico inicial.',
        ]);
        $this->assertSame(1, DB::table('tecnico_jefe_tecnico')->where('reporte_id', $reportId)->whereNull('fecha_finalizacion')->count());

        $this->actingAs($chief)
            ->patchJson("/api/reportes/{$reportId}/estado", ['status' => 'Diagnóstico', 'action_report' => 'Se realizaron pruebas de alimentación y conectividad.'])
            ->assertUnprocessable();

        $this->actingAs($chief)
            ->patchJson("/api/reportes/{$reportId}/estado", [
                'status' => 'Diagnóstico',
                'action_report' => 'Se realizaron pruebas de alimentación y conectividad.',
                'final_diagnosis' => 'La fuente de alimentación requiere reemplazo.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'Diagnóstico');

        $this->actingAs($chief)
            ->patchJson("/api/reportes/{$reportId}/estado", [
                'status' => 'Finalizado',
                'action_report' => 'Se reemplazó la fuente y se verificó la operación.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'Finalizado');
    }

    public function test_maintenance_calculates_next_date_from_asset_service_frequency(): void
    {
        $tech = User::findOrFail(5);
        $asset = Asset::findOrFail(1);
        $this->actingAs($tech)->postJson('/api/mantenimientos', ['asset_id' => $asset->id, 'maintenance_date' => '2026-09-01', 'detail' => 'Limpieza de ventiladores y verificación de voltajes.'])->assertCreated()->assertJsonPath('data.proxima_fecha', '2026-11-30T00:00:00.000000Z');
    }

    public function test_network_scan_does_not_pretend_to_run_from_vlan10(): void
    {
        $leader = User::findOrFail(4);
        $this->actingAs($leader)->postJson('/api/infraestructura/scan', ['origin' => 'vlan10'])->assertUnprocessable();
    }

    public function test_spa_root_view_loads_successfully(): void
    {
        $this->actingAs(User::findOrFail(1))->get('/')->assertOk()->assertSee('PreventiTI')->assertSee('app-sidebar');
    }
}
