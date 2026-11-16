<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AssetRetirementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_retiring_and_restoring_a_failed_asset_preserves_its_condition_and_history(): void
    {
        $asset = Asset::findOrFail(1);
        $asset->update(['estado_activo' => 'Fuera de Servicio']);
        $before = [];
        foreach (['reportes', 'reporte_activo', 'mantenimientos', 'consumibles', 'usuarios'] as $table) $before[$table] = DB::table($table)->get()->toJson();
        $this->actingAs(User::findOrFail(1))->patchJson("/api/activos/{$asset->id}/toggle", ['expected_state' => 'Fuera de Servicio'])->assertOk();
        $this->assertDatabaseHas('activos', ['id' => $asset->id, 'estado_activo' => 'Retirado', 'estado_previo_retiro' => 'Fuera de Servicio']);
        $this->patchJson("/api/activos/{$asset->id}/toggle", ['expected_state' => 'Retirado'])->assertOk();
        $this->assertDatabaseHas('activos', ['id' => $asset->id, 'estado_activo' => 'Fuera de Servicio', 'estado_previo_retiro' => null]);
        $this->assertFalse($asset->fresh()->retirado);
        $this->assertFalse($asset->fresh()->activo);
        foreach ($before as $table => $rows) $this->assertSame($rows, DB::table($table)->get()->toJson(), $table);
    }

    public function test_operational_and_maintenance_states_are_restored_exactly(): void
    {
        $this->actingAs(User::findOrFail(1));
        foreach (['Operativo', 'En Mantenimiento'] as $state) {
            $asset = Asset::where('estado_activo', $state)->firstOrFail();
            $this->patchJson("/api/activos/{$asset->id}/toggle", ['expected_state' => $state])->assertOk();
            $this->patchJson("/api/activos/{$asset->id}/toggle", ['expected_state' => 'Retirado'])->assertOk();
            $this->assertSame($state, $asset->fresh()->estado_activo);
        }
    }

    public function test_a_stale_or_repeated_request_does_not_reverse_the_previous_action(): void
    {
        $this->actingAs(User::findOrFail(1));
        $this->patchJson('/api/activos/1/toggle', ['expected_state' => 'Operativo'])->assertOk();
        $this->patchJson('/api/activos/1/toggle', ['expected_state' => 'Operativo'])->assertStatus(409);
        $this->assertDatabaseHas('activos', ['id' => 1, 'estado_activo' => 'Retirado', 'estado_previo_retiro' => 'Operativo']);
    }

    public function test_old_retirement_without_a_recorded_condition_does_not_assume_the_asset_works(): void
    {
        Asset::findOrFail(1)->update(['estado_activo' => 'Retirado']);
        // Imported retirements may lack their preceding condition. A new SQL
        // transition now records it automatically, so reproduce that old NULL.
        if (\App\Services\DatabaseWorkflow::enabled()) Asset::whereKey(1)->update(['estado_previo_retiro' => null]);
        $this->actingAs(User::findOrFail(1))->patchJson('/api/activos/1/toggle', ['expected_state' => 'Retirado'])->assertOk();
        $this->assertDatabaseHas('activos', ['id' => 1, 'estado_activo' => 'Fuera de Servicio']);
    }

    public function test_leader_can_create_archive_and_restore_from_the_shared_inventory(): void
    {
        $this->actingAs(User::findOrFail(4));
        $this->getJson('/api/auth/me')->assertJsonPath('permissions.can_manage_assets', true)
            ->assertJsonPath('permissions.can_edit_assets', false);
        $asset = $this->postJson('/api/activos', [
            'categoria_id' => 1, 'ubicacion_id' => 5, 'nombre' => 'Equipo de gerencia', 'serie' => 'HIST-01',
        ])->assertCreated()->json('data');
        $id = $asset['id'];
        $this->assertSame('management', collect($this->getJson('/api/floorplan')->json('data.assets'))->firstWhere('id', $id)['room_id']);
        $report = $this->postJson('/api/reportes', [
            'asset_id' => $id, 'priority' => 'Media', 'description' => 'Revisar conexión del equipo.',
        ])->assertCreated()->json('data.id');
        $maintenance = $this->postJson('/api/mantenimientos', [
            'asset_id' => $id, 'maintenance_date' => now('America/Montevideo')->toDateString(), 'detail' => 'Verificación de conectividad.',
        ])->assertCreated()->json('data.id');

        $this->deleteJson("/api/activos/{$id}")->assertOk();
        $date = Asset::findOrFail($id)->fecha_baja;
        $this->assertNotNull($date);
        $this->deleteJson("/api/activos/{$id}")->assertOk();
        $this->assertEquals($date, Asset::findOrFail($id)->fecha_baja);
        $listing = $this->getJson('/api/activos?incluir_historico=1')->assertOk()->json();
        $this->assertNotContains($id, array_column($listing['data'], 'id'));
        $this->assertContains($id, array_column($listing['historico'], 'id'));
        $this->assertNotContains($id, array_column($this->getJson('/api/floorplan')->json('data.assets'), 'id'));
        $this->assertNotContains($id, array_column($this->getJson('/api/reportes/activos-disponibles')->json('data'), 'id'));
        $this->getJson("/api/activos/{$id}")->assertOk()->assertJsonPath('data.asset.reportes.0.id', $report)
            ->assertJsonPath('data.asset.mantenimientos.0.id', $maintenance);
        $this->actingAs(User::findOrFail(5))->getJson("/api/activos/{$id}")->assertOk()->assertJsonCount(0, 'data.asset.reportes');

        $this->actingAs(User::findOrFail(4))->patchJson("/api/activos/{$id}/toggle", ['expected_state' => 'Retirado'])->assertOk();
        $this->assertDatabaseHas('activos', ['id' => $id, 'estado_activo' => 'Operativo', 'fecha_baja' => null]);
        $listing = $this->getJson('/api/activos?incluir_historico=1')->assertOk()->json();
        $this->assertContains($id, array_column($listing['data'], 'id'));
        $this->assertNotContains($id, array_column($listing['historico'], 'id'));
        $this->assertContains($id, array_column($this->getJson('/api/floorplan')->json('data.assets'), 'id'));
    }

    public function test_legacy_retirements_and_new_devices_without_history_are_archived(): void
    {
        $this->assertTrue((new Asset(['estado_activo' => ' retirado ']))->retirado);
        Asset::findOrFail(1)->update(['estado_activo' => \App\Services\DatabaseWorkflow::enabled() ? 'Retirado' : ' retirado ']);
        Asset::findOrFail(2)->update(['estado_activo' => 'Fuera de Servicio']);
        $this->actingAs(User::findOrFail(1));
        $asset = $this->postJson('/api/activos', [
            'categoria_id' => 1, 'ubicacion_id' => 1, 'nombre' => 'Terminal temporal', 'serie' => 'HIST-02',
        ])->assertCreated()->json('data.id');
        $this->deleteJson("/api/activos/{$asset}")->assertOk();
        $listing = $this->getJson('/api/activos?incluir_historico=1')->assertOk()->json();
        $this->assertContains(1, array_column($listing['historico'], 'id'));
        $this->assertContains($asset, array_column($listing['historico'], 'id'));
        $this->assertContains(2, array_column($listing['data'], 'id'));
        $this->assertNotContains(1, array_column($this->getJson('/api/activos')->json('data'), 'id'));
        $this->assertNotContains(1, array_column($this->getJson('/api/floorplan')->json('data.assets'), 'id'));
    }

    public function test_other_roles_cannot_create_retire_or_restore(): void
    {
        foreach ([5, 9] as $id) {
            $this->actingAs(User::findOrFail($id));
            $this->getJson('/api/auth/me')->assertJsonPath('permissions.can_manage_assets', false);
            $this->postJson('/api/activos')->assertForbidden();
            $this->patchJson('/api/activos/1/toggle')->assertForbidden();
            $this->deleteJson('/api/activos/1')->assertForbidden();
        }
        $this->assertDatabaseHas('activos', ['id' => 1, 'estado_activo' => 'Operativo']);
    }
}
