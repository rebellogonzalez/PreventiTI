<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Asset;
use App\Models\Audit;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthAndRBACTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_unauthenticated_user_cannot_access_internal_system(): void
    {
        $this->getJson('/api/dashboard/stats')->assertStatus(401);
        $this->getJson('/api/activos')->assertStatus(401);
        $this->getJson('/api/reportes')->assertStatus(401);
        $this->get('/')->assertRedirect('/login');
    }

    public function test_employee_login_has_limited_areas(): void
    {
        User::where('email', 'laura.mendez@preventiti.local')->firstOrFail()->update(['password' => 'test-login-password']);
        $response = $this->postJson('/api/auth/login', ['email' => 'laura.mendez@preventiti.local', 'password' => 'test-login-password']);
        $response->assertOk()->assertJsonFragment(['role' => 'empleado', 'hierarchy' => 4]);
        $this->assertSame(['dashboard', 'reportes'], $response->json('allowed_areas'));
        $this->getJson('/api/reportes')->assertOk();
    }

    public function test_employee_is_denied_restricted_modules(): void
    {
        $employee = User::findOrFail(9);
        $this->actingAs($employee)->getJson('/api/estadisticas')->assertStatus(403);
        $this->actingAs($employee)->getJson('/api/infraestructura/status')->assertStatus(403);
        $this->actingAs($employee)->getJson('/api/mantenimientos')->assertStatus(403);
        $this->actingAs($employee)->getJson('/api/activos')->assertStatus(403);
    }

    public function test_reporting_catalog_exposes_categories_without_inventory_details_and_excludes_retired_assets(): void
    {
        $employee = User::findOrFail(9);
        $asset = Asset::with('categoria')->findOrFail(1);
        $response = $this->actingAs($employee)->getJson('/api/reportes/activos-disponibles')->assertOk();
        $entry = collect($response->json('data'))->firstWhere('id', $asset->id);
        $this->assertNotNull($entry);
        $this->assertSame($asset->categoria->nombre, $entry['category_name']);
        $this->assertEqualsCanonicalizing(['id', 'code', 'name', 'category_name', 'location_name', 'active'], array_keys($entry));
        $this->getJson('/api/activos')->assertForbidden();

        $asset->update(['estado_activo' => 'Retirado']);
        $catalog = $this->getJson('/api/reportes/activos-disponibles')->assertOk()->json('data');
        $this->assertFalse(collect($catalog)->contains('id', $asset->id));
    }

    public function test_idor_prevention_for_report_and_asset(): void
    {
        $laura = User::findOrFail(9);
        $esteban = User::findOrFail(10);
        $tech = User::findOrFail(8);
        $audit = Audit::create(['fecha_auditoria' => now(), 'descripcion' => 'Reporte de prueba IDOR.']);
        $report = Report::create(['auditoria_id' => $audit->id, 'estado_reporte' => 'Recibido/Derivado', 'urgencia' => 'Medio', 'descripcion' => 'Falla en teclado numérico de caja.', 'fecha_creacion' => now()]);
        $report->activos()->attach(1);
        $report->creadores()->attach($laura->id);
        $this->actingAs($esteban)->getJson("/api/reportes/{$report->id}")->assertStatus(403);
        $this->actingAs($tech)->getJson("/api/reportes/{$report->id}")->assertStatus(403);
        $this->actingAs($laura)->getJson('/api/activos/77')->assertStatus(403);
    }

    public function test_reporting_catalog_and_submission_respect_room_access_for_each_role(): void
    {
        $restrictedRooms = ['Sala de servidores / IT', 'Sala de personal técnico', 'Oficina de gerencia y coordinación', 'Administración y recursos humanos'];
        $assets = Asset::inInventory()->with('ubicacionDetalle')->get();
        $restricted = $assets->whereIn('ubicacionDetalle.sala', $restrictedRooms);
        $this->assertCount(4, $restricted->pluck('ubicacionDetalle.sala')->unique());
        $payload = ['priority' => 'Media', 'description' => 'El dispositivo presenta una falla.'];
        $employee = User::findOrFail(9);
        $this->actingAs($employee);
        $catalog = $this->getJson('/api/reportes/activos-disponibles')->assertOk()->json('data');
        $this->assertEqualsCanonicalizing($assets->diff($restricted)->modelKeys(), array_column($catalog, 'id'));
        $before = [Report::count(), Alert::count(), Audit::count()];
        foreach ($restricted->unique('ubicacionDetalle.sala') as $asset) {
            $this->postJson('/api/reportes', ['asset_id' => $asset->id, ...$payload])
                ->assertUnprocessable()->assertJsonValidationErrors('asset_id');
        }
        $this->assertSame($before, [Report::count(), Alert::count(), Audit::count()]);

        foreach ([1, 4, 5] as $userId) {
            $this->actingAs(User::findOrFail($userId));
            $catalog = $this->getJson('/api/reportes/activos-disponibles')->assertOk()->json('data');
            $this->assertEqualsCanonicalizing($assets->modelKeys(), array_column($catalog, 'id'));
            $this->postJson('/api/reportes', ['asset_id' => $restricted->first()->id, ...$payload])->assertCreated();
        }
    }

    public function test_moving_a_device_revokes_employee_reporting_access_before_submission(): void
    {
        $this->actingAs(User::findOrFail(9));
        $asset = Asset::findOrFail(1);
        $this->getJson('/api/reportes/activos-disponibles')->assertOk()->assertJsonFragment(['id' => $asset->id]);
        $asset->ubicacionDetalle()->update(['sala' => 'Sala de personal técnico']);
        $this->postJson('/api/reportes', ['asset_id' => $asset->id, 'priority' => 'Alta', 'description' => 'Reporte desde una lista anterior.'])
            ->assertUnprocessable()->assertJsonValidationErrors('asset_id');
        $asset->ubicacionDetalle()->delete();
        $this->getJson('/api/reportes/activos-disponibles')->assertOk()->assertJsonMissing(['id' => $asset->id]);
    }

    public function test_authorized_operations_use_hito_tables(): void
    {
        $admin = User::findOrFail(1);
        $leader = User::findOrFail(4);
        $employee = User::findOrFail(9);
        $this->actingAs($admin)->postJson('/api/activos', ['categoria_id' => 1, 'ubicacion_id' => 1, 'nombre' => 'Terminal POS autorizada', 'serie' => 'SN-AUTH-001'])->assertCreated();
        $response = $this->actingAs($employee)->postJson('/api/reportes', ['asset_id' => 1, 'priority' => 'Alta', 'description' => 'El lector de códigos no enciende al iniciar la caja.']);
        $response->assertCreated()->assertJsonPath('data.asset_category', Asset::findOrFail(1)->categoria->nombre);
        $reportId = $response->json('data.id');
        $this->assertDatabaseHas('usuario_reporte', ['usuario_id' => $employee->id, 'reporte_id' => $reportId]);
        $alert = Alert::latest('id')->firstOrFail();
        $this->actingAs($leader)->getJson('/api/notificaciones')->assertOk()->assertJsonFragment(['id' => $alert->id]);
        $this->actingAs($leader)->patchJson("/api/notificaciones/{$alert->id}/leer")->assertOk()->assertJsonPath('data.status', 'Leída');
    }

    public function test_logout_invalidates_session(): void
    {
        $admin = User::findOrFail(1);
        $this->actingAs($admin)->getJson('/api/auth/me')->assertOk();
        $this->postJson('/api/auth/logout')->assertOk()->assertJsonFragment(['redirect' => '/login']);
        $this->getJson('/api/dashboard/stats')->assertStatus(401);
    }
}
