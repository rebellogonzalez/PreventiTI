<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Audit;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class AssetCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function editData(int $id, array $changes = []): array
    {
        $asset = $this->getJson("/api/activos/{$id}")->assertOk()->json('data.asset');

        return array_replace([
            'categoria_id' => $asset['categoria_id'], 'ubicacion_id' => $asset['ubicacion_id'],
            'nombre' => $asset['nombre'], 'serie' => $asset['serie'], 'responsable' => $asset['responsable'],
            'frecuencia_service' => $asset['frecuencia_service'], 'expected_version' => $asset['edit_version'],
        ], $changes);
    }

    public function test_full_crud_persists_punctuation_and_reloads_the_updated_values(): void
    {
        $this->actingAs(User::findOrFail(1));
        $data = ['categoria_id' => 1, 'ubicacion_id' => 1, 'nombre' => 'Terminal v2.5; "Caja" <A&B>',
            'serie' => 'CRUD-SN-001', 'responsable' => 'Ing. Ana; Operación & Soporte', 'frecuencia_service' => 120];
        $created = $this->postJson('/api/activos', $data)->assertCreated()
            ->assertJsonPath('data.name', $data['nombre'])->assertJsonPath('data.responsible', $data['responsable'])->json('data');
        $id = $created['id'];
        $this->assertSame($data['nombre'], Asset::findOrFail($id)->nombre);
        $this->postJson('/api/activos', $data)->assertUnprocessable()->assertJsonValidationErrors('serie');
        $this->getJson('/api/activos')->assertOk()->assertJsonFragment(['serial' => 'CRUD-SN-001']);

        $changes = $this->editData($id, ['nombre' => 'Notebook v3.2; Equipo "B"', 'categoria_id' => 2,
            'ubicacion_id' => 5, 'serie' => 'CRUD-SN-002', 'responsable' => 'Lic. Pérez; Administración', 'frecuencia_service' => 180]);
        $this->patchJson("/api/activos/{$id}", $changes)->assertOk()
            ->assertJsonPath('data.code', $created['code'])->assertJsonPath('data.name', $changes['nombre']);
        $this->getJson("/api/activos/{$id}")->assertOk()->assertJsonPath('data.asset.nombre', $changes['nombre'])
            ->assertJsonPath('data.asset.responsable', $changes['responsable'])->assertJsonPath('data.asset.ubicacion_id', 5);
        $this->assertDatabaseHas('activos', ['id' => $id, 'serial' => 'CRUD-SN-002', 'categoria_id' => 2, 'frecuencia_service' => 180]);
        $this->assertDatabaseHas('activo_ubicaciones', ['activo_id' => $id, 'sala' => 'Oficina de gerencia y coordinación']);
        $this->getJson('/api/reportes/activos-disponibles')->assertOk()->assertJsonFragment(['name' => $changes['nombre']]);

        $this->patchJson("/api/activos/{$id}/toggle", ['expected_state' => 'Operativo'])->assertOk();
        $this->getJson("/api/activos/{$id}")->assertOk()->assertJsonPath('data.asset.estado', 'Retirado');
        $this->assertDatabaseHas('activos', ['id' => $id, 'estado_previo_retiro' => 'Operativo']);
        $this->patchJson("/api/activos/{$id}/toggle", ['expected_state' => 'Retirado'])->assertOk();
        $this->assertSame('Operativo', Asset::findOrFail($id)->estado_activo);
        $this->assertTrue(Audit::where('descripcion', 'like', "Edición de activo {$created['code']}%")
            ->where('descripcion', 'like', '%"antes"%')->where('descripcion', 'like', '%"despues"%')->exists());
    }

    public function test_editing_legacy_asset_keeps_code_original_description_history_and_state(): void
    {
        $this->actingAs(User::findOrFail(1));
        $asset = Asset::findOrFail(1);
        $original = $asset->getAttributes();
        $code = $asset->codigo;
        $history = [];
        foreach (['reportes', 'reporte_activo', 'mantenimientos', 'tareas_calendario', 'usuarios', 'consumibles'] as $table) {
            $history[$table] = DB::table($table)->get()->toJson();
        }
        $payload = $this->editData(1, ['nombre' => 'Actualizado. Con puntuación;', 'responsable' => 'Ing. A. Rodríguez',
            'codigo' => 'NO-CAMBIAR', 'estado_activo' => 'Retirado', 'id' => 999, 'fecha_alta' => '2000-01-01']);
        $this->patchJson('/api/activos/1', $payload)->assertOk();
        $asset->refresh();
        $this->assertSame($code, $asset->codigo);
        $this->assertSame($original['descripcion'], json_decode($asset->descripcion, true)['descripcion_original']);
        $this->assertSame($original['estado_activo'], $asset->estado_activo);
        $this->assertSame($original['fecha_alta'], $asset->getRawOriginal('fecha_alta'));
        foreach ($history as $table => $rows) $this->assertSame($rows, DB::table($table)->get()->toJson(), $table);

        $this->patchJson('/api/activos/1', $this->editData(1, ['nombre' => 'Segunda edición']))->assertOk();
        $this->assertSame($original['descripcion'], json_decode($asset->fresh()->descripcion, true)['descripcion_original']);
    }

    public function test_same_serial_is_allowed_but_another_assets_serial_is_rejected_without_changes(): void
    {
        $this->actingAs(User::findOrFail(1));
        $this->patchJson('/api/activos/1', $this->editData(1, ['nombre' => 'Conserva su serie']))->assertOk();
        $before = Asset::findOrFail(1)->getAttributes();
        $audits = Audit::count();
        $this->patchJson('/api/activos/1', $this->editData(1, ['serie' => Asset::findOrFail(2)->serial, 'ubicacion_id' => 5]))
            ->assertUnprocessable()->assertJsonValidationErrors('serie');
        $this->assertSame($before, Asset::findOrFail(1)->getAttributes());
        $this->assertSame($audits, Audit::count());
    }

    public function test_optional_values_are_preserved_when_omitted_and_can_be_cleared_explicitly(): void
    {
        $this->actingAs(User::findOrFail(1));
        $original = Asset::findOrFail(1);
        $payload = $this->editData(1, ['nombre' => 'Solo cambia el nombre']);
        unset($payload['responsable'], $payload['frecuencia_service']);
        $this->patchJson('/api/activos/1', $payload)->assertOk()
            ->assertJsonPath('data.responsible', $original->responsable)
            ->assertJsonPath('data.periodicity_days', $original->frecuencia_service);
        $this->patchJson('/api/activos/1', $this->editData(1, ['responsable' => null]))->assertOk()
            ->assertJsonPath('data.responsible', 'No asignado');
        $this->patchJson('/api/activos/1', $this->editData(1, ['responsable' => '0']))->assertOk()
            ->assertJsonPath('data.responsible', '0');
    }

    public function test_invalid_edit_is_rejected_atomically_and_unknown_asset_returns_not_found(): void
    {
        $this->actingAs(User::findOrFail(1));
        $before = Asset::findOrFail(1)->getAttributes();
        $this->patchJson('/api/activos/1', $this->editData(1, ['categoria_id' => 99999, 'ubicacion_id' => 99,
            'nombre' => '   ', 'serie' => '', 'responsable' => str_repeat('X', 151), 'frecuencia_service' => 0]))
            ->assertUnprocessable()->assertJsonValidationErrors(['categoria_id', 'ubicacion_id', 'nombre', 'serie', 'responsable', 'frecuencia_service']);
        $this->assertSame($before, Asset::findOrFail(1)->getAttributes());
        $this->patchJson('/api/activos/1', [])->assertUnprocessable()->assertJsonValidationErrors('expected_version');
        $this->patchJson('/api/activos/999999', [])->assertNotFound();
    }

    public function test_only_authenticated_administrators_can_edit_assets(): void
    {
        $this->patchJson('/api/activos/1', [])->assertUnauthorized();
        $before = Asset::findOrFail(1)->getAttributes();
        foreach ([4, 5, 9] as $id) {
            $this->actingAs(User::findOrFail($id))->patchJson('/api/activos/1', ['nombre' => 'No permitido'])->assertForbidden();
        }
        $this->assertSame($before, Asset::findOrFail(1)->getAttributes());
    }

    public function test_stale_form_cannot_overwrite_another_edit_or_retirement(): void
    {
        $this->actingAs(User::findOrFail(1));
        $first = $this->editData(1, ['nombre' => 'Primer cambio']);
        $this->patchJson('/api/activos/1', $first)->assertOk();
        $this->patchJson('/api/activos/1', array_replace($first, ['nombre' => 'Cambio obsoleto']))->assertConflict();
        $this->assertSame('Primer cambio', Asset::findOrFail(1)->nombre);
        $next = $this->editData(1, ['nombre' => 'Cambio de ficha']);
        $this->patchJson('/api/activos/1/toggle', ['expected_state' => 'Operativo'])->assertOk();
        $this->patchJson('/api/activos/1', $next)->assertConflict();
        $this->patchJson('/api/activos/1', $this->editData(1, ['nombre' => 'Ficha de retirado']))->assertOk();
        $this->assertDatabaseHas('activos', ['id' => 1, 'estado_activo' => 'Retirado', 'estado_previo_retiro' => 'Operativo']);
    }

    public function test_location_change_invalidates_map_position_and_custom_location_can_be_kept(): void
    {
        $this->actingAs(User::findOrFail(1));
        $location = Location::findOrFail(1);
        $position = ['x' => 0.3, 'z' => 0.5, 'elevation' => 0, 'rotation' => 90, 'floor' => $location->piso, 'room' => $location->sala];
        $this->patchJson('/api/floorplan/assets/1/position', $position)->assertOk();
        $this->patchJson('/api/activos/1', $this->editData(1, ['ubicacion_id' => 5]))->assertOk();
        $map = $this->getJson('/api/floorplan')->assertOk()->json('data.assets');
        $this->assertTrue(collect($map)->firstWhere('id', 1)['position_stale']);
        $this->getJson('/api/activos/1')->assertOk()->assertJsonPath('data.asset.map_position', null);
        $this->assertDatabaseHas('asset_map_positions', ['activo_id' => 1, 'x' => 0.3]);

        $location->update(['sala' => 'Sala histórica']);
        Asset::findOrFail(1)->update(['ubicacion' => 'Planta principal - Sala histórica']);
        $this->patchJson('/api/activos/1', $this->editData(1, ['nombre' => 'Conserva ubicación histórica', 'ubicacion_id' => null]))->assertOk();
        $this->assertDatabaseHas('activo_ubicaciones', ['activo_id' => 1, 'sala' => 'Sala histórica']);
        $this->assertSame('Planta principal - Sala histórica', Asset::findOrFail(1)->ubicacion);
    }

    public function test_category_changes_create_missing_consumable_once_and_preserve_existing_reading(): void
    {
        $this->actingAs(User::findOrFail(1));
        $this->patchJson('/api/activos/1', $this->editData(1, ['categoria_id' => 4]))->assertOk()->assertJsonPath('data.has_consumable', true);
        $this->assertSame(1, Asset::findOrFail(1)->consumibles()->count());
        Asset::findOrFail(1)->consumibles()->update(['nivel' => 17]);
        $this->patchJson('/api/activos/1', $this->editData(1, ['nombre' => 'Conserva insumo']))->assertOk();
        $this->patchJson('/api/activos/1', $this->editData(1, ['categoria_id' => 2]))->assertOk()
            ->assertJsonPath('data.has_consumable', true)->assertJsonPath('data.consumable_level', 17);
        $this->assertSame(1, Asset::findOrFail(1)->consumibles()->count());
    }

    public function test_a_failure_saving_audit_rolls_back_asset_location_and_consumable(): void
    {
        $this->actingAs(User::findOrFail(1));
        $before = Asset::findOrFail(1)->getAttributes();
        $location = Location::findOrFail(1)->getAttributes();
        $data = $this->editData(1, ['categoria_id' => 4, 'ubicacion_id' => 5, 'nombre' => 'Debe revertirse']);
        Audit::creating(function () { throw new RuntimeException('Fallo de auditoría de prueba'); });
        $this->withoutExceptionHandling();
        try {
            $this->patchJson('/api/activos/1', $data);
            $this->fail('La auditoría debe impedir confirmar una edición incompleta.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Fallo de auditoría de prueba', $exception->getMessage());
        } finally {
            Audit::flushEventListeners();
        }
        $this->assertSame($before, Asset::findOrFail(1)->getAttributes());
        $this->assertSame($location, Location::findOrFail(1)->getAttributes());
        $this->assertSame(0, Asset::findOrFail(1)->consumibles()->count());
    }

    public function test_cookie_authenticated_edit_requires_csrf_outside_testing(): void
    {
        $this->actingAs(User::findOrFail(1));
        $data = $this->editData(1, ['nombre' => 'Cambio protegido']);
        $this->app['env'] = 'local';
        $this->patchJson('/api/activos/1', $data)->assertStatus(419);
        $this->withSession(['_token' => 'crud-csrf'])->withHeader('X-CSRF-TOKEN', 'crud-csrf')
            ->patchJson('/api/activos/1', $data)->assertOk();
    }
}
