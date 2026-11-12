<?php

namespace Database\Seeders;

use App\Models\Alert;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetConsumable;
use App\Models\Audit;
use App\Models\CalendarTask;
use App\Models\Location;
use App\Models\Maintenance;
use App\Models\Report;
use App\Models\TechnicalChief;
use App\Models\TechnicalChiefName;
use App\Models\Technician;
use App\Models\User;
use App\Models\UserAddress;
use App\Models\UserName;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $data = json_decode(file_get_contents(database_path('data/seed_data.json')), true, 512, JSON_THROW_ON_ERROR);

        // The JSON retains the previous data set as a traceable source. These are the counts published in Anexo A.
        $officialCategories = [
            1 => ['name' => 'Terminales', 'description' => 'Computadoras.', 'consumable' => false],
            2 => ['name' => 'Equipos portátiles', 'description' => 'Seis notebooks técnicos, una del técnico líder y tres de gerencia.', 'consumable' => false],
            4 => ['name' => 'Impresión', 'description' => 'Equipos de impresión.', 'consumable' => true],
            5 => ['name' => 'Cartelería digital', 'description' => 'Equipos de cartelería digital.', 'consumable' => false],
            6 => ['name' => 'Seguridad CCTV', 'description' => 'Equipos relacionados con videovigilancia.', 'consumable' => false],
            7 => ['name' => 'Climatización IoT', 'description' => 'Dispositivos de climatización con capacidad de gestión IoT.', 'consumable' => false],
            10 => ['name' => 'Protección eléctrica', 'description' => 'Equipos de protección eléctrica.', 'consumable' => false],
        ];

        foreach ($officialCategories as $id => $category) {
            AssetCategory::create(['id' => $id, 'nombre' => $category['name'], 'descripcion' => $category['description'], 'requiere_consumible' => $category['consumable']]);
        }

        $officialUsers = [...$data['users'],
            ['id' => 39, 'name' => 'Técnico Oficial de Software 01', 'email' => 'tecnico.software01@preventiti.local', 'role' => 'tecnico', 'sector' => 'Soporte Técnico', 'specialty' => 'Software'],
            ['id' => 40, 'name' => 'Técnico Oficial de Redes 01', 'email' => 'tecnico.redes01@preventiti.local', 'role' => 'tecnico', 'sector' => 'Soporte Técnico', 'specialty' => 'Redes/Infraestructura'],
        ];

        foreach ($officialUsers as $entry) {
            $user = User::create([
                'id' => $entry['id'],
                'nombre_completo' => $entry['name'],
                'email' => $entry['email'],
                'direccion' => $entry['sector'] ?? null,
                'password' => Hash::make(\App\Support\AccountPassword::generate($entry['email'])),
                'rol' => $entry['role'] === 'administrador' ? 'Administrador' : 'Empleado',
                'activo' => true,
            ]);
            [$name, $lastName] = array_pad(preg_split('/\s+/', trim($entry['name']), 2), 2, null);
            UserName::create(['usuario_id' => $user->id, 'nombre' => $name, 'apellido' => $lastName]);
            UserAddress::create(['usuario_id' => $user->id, 'calle' => $entry['sector'] ?? null, 'numero_calle' => isset($entry['sector']) ? 'S/N' : null]);
            $isChief = ! empty($entry['is_leader']) || $entry['id'] === 4;
            $isTechnician = $isChief || $entry['role'] === 'tecnico';
            if ($isTechnician) {
                $specialty = mb_strtolower($entry['specialty'] ?? '');
                $type = str_contains($specialty, 'red') || str_contains($specialty, 'infra') ? 'Redes/Infraestructura' : (str_contains($specialty, 'software') ? 'Software' : 'Hardware');
                Technician::create(['id' => $user->id, 'tipo_tecnico' => $type]);
            }
            if ($isChief) {
                TechnicalChief::create(['id' => $user->id, 'nombre_completo' => $entry['name']]);
                TechnicalChiefName::create(['jefe_id' => $user->id, 'nombre' => $name, 'apellido' => $lastName]);
            }
        }

        $locations = Location::mapRooms()->map(fn (array $room) => ['piso' => $room['floor'], 'sala' => $room['location']]);
        $assetLocationOverrides = [
            30 => 5, // notebook del Jefe Técnico
            42 => 3, // cartelería de atención al cliente
            84 => 7,
            85 => 7,
        ];
        $officialAssetIds = [...range(1, 33), ...range(36, 58), ...range(73, 78)];
        $officialAssets = collect($data['assets'])
            ->whereIn('id', $officialAssetIds)
            ->values()
            ->all();
        $officialAssets = [...$officialAssets,
            ['id' => 79, 'category_id' => 1, 'serial' => 'SN-TER-2026-026', 'code' => 'ACT-TER-026', 'name' => 'Terminal oficial 26', 'responsible' => 'Sin asignar', 'location_id' => 1, 'active' => true, 'state' => 'Operativo', 'periodicity_days' => 90],
            ['id' => 80, 'category_id' => 1, 'serial' => 'SN-TER-2026-027', 'code' => 'ACT-TER-027', 'name' => 'Terminal oficial 27', 'responsible' => 'Sin asignar', 'location_id' => 1, 'active' => true, 'state' => 'Operativo', 'periodicity_days' => 90],
            ['id' => 81, 'category_id' => 1, 'serial' => 'SN-TER-2026-028', 'code' => 'ACT-TER-028', 'name' => 'Terminal oficial 28', 'responsible' => 'Sin asignar', 'location_id' => 1, 'active' => true, 'state' => 'Operativo', 'periodicity_days' => 90],
            ['id' => 82, 'category_id' => 1, 'serial' => 'SN-TER-2026-029', 'code' => 'ACT-TER-029', 'name' => 'Terminal oficial 29', 'responsible' => 'Sin asignar', 'location_id' => 1, 'active' => true, 'state' => 'Operativo', 'periodicity_days' => 90],
            ['id' => 83, 'category_id' => 1, 'serial' => 'SN-TER-2026-030', 'code' => 'ACT-TER-030', 'name' => 'Terminal oficial 30', 'responsible' => 'Sin asignar', 'location_id' => 1, 'active' => true, 'state' => 'Operativo', 'periodicity_days' => 90],
            ['id' => 84, 'category_id' => 2, 'serial' => 'SN-NBK-2026-009', 'code' => 'ACT-NBK-009', 'name' => 'Notebook técnico oficial 05', 'responsible' => 'Sin asignar', 'location_id' => 7, 'active' => true, 'state' => 'Operativo', 'periodicity_days' => 180],
            ['id' => 85, 'category_id' => 2, 'serial' => 'SN-NBK-2026-010', 'code' => 'ACT-NBK-010', 'name' => 'Notebook técnico oficial 06', 'responsible' => 'Sin asignar', 'location_id' => 7, 'active' => true, 'state' => 'Operativo', 'periodicity_days' => 180],
        ];
        $officialAssetIds = collect($officialAssets)->pluck('id')->all();

        $usersByName = User::with('nombres')->get()->keyBy(fn (User $user) => $user->nombre);
        foreach ($officialAssets as $entry) {
            $locationId = $assetLocationOverrides[$entry['id']] ?? $entry['location_id'];
            $location = $locations->get($locationId);
            $locationName = trim(($location['piso'] ?? '').' - '.($location['sala'] ?? ''), ' -');
            $asset = new Asset(['categoria_id' => $entry['category_id'], 'serial' => $entry['serial'], 'ubicacion' => $locationName, 'descripcion' => "Código legado: {$entry['code']}. Nombre legado: {$entry['name']}. Responsable legado: {$entry['responsible']}.", 'estado_activo' => ! empty($entry['active']) ? $entry['state'] : 'Retirado', 'fecha_alta' => '2026-01-01', 'frecuencia_service' => $entry['periodicity_days']]);
            // IDs are part of the traceability of reports and maintenance records, so retain the supplied IDs.
            $asset->id = $entry['id'];
            $asset->responsable_id = $usersByName->get($entry['responsible'])?->id;
            $asset->save();
            Location::create(['activo_id' => $asset->id, 'piso' => $location['piso'] ?? null, 'sala' => $location['sala'] ?? null]);
            if (! empty($entry['has_consumable']) && ! empty($entry['consumable_type'])) AssetConsumable::create(['activo_id' => $asset->id, 'nombre' => 'Consumible de '.$asset->codigo, 'tipo' => $entry['consumable_type'], 'nivel' => $entry['consumable_level'] ?? 100]);
        }

        $chiefId = TechnicalChief::query()->value('id');
        foreach ($data['reports'] as $entry) {
            $urgency = match ($entry['priority']) {'Critica', 'Crítica' => 'Critico', 'Alta' => 'Alto', 'Baja' => 'Bajo', default => 'Medio'};
            $audit = Audit::create(['fecha_auditoria' => $entry['created_at'], 'descripcion' => 'Creación de reporte legado '.$entry['code'].'.']);
            $assetIsOfficial = in_array($entry['asset_id'], $officialAssetIds, true);
            $report = Report::create(['id' => $entry['id'], 'jefe_id' => $chiefId, 'auditoria_id' => $audit->id, 'historial' => $assetIsOfficial ? 'Importado desde datos de demostración.' : 'Historial importado: el activo original quedó fuera del catálogo oficial del Anexo A.', 'estado_reporte' => 'Recibido/Derivado', 'urgencia' => $urgency, 'descripcion' => $entry['description'], 'fecha_creacion' => $entry['created_at'], 'created_at' => $entry['created_at'], 'updated_at' => $entry['updated_at']]);
            if ($assetIsOfficial) $report->activos()->attach($entry['asset_id']);
            $report->creadores()->attach($entry['creator_id']);
            $alert = Alert::create(['nivel_alerta' => $urgency, 'tipo_alerta' => 'Reporte', 'fecha_generacion' => $entry['created_at'], 'estado_alerta' => 'Pendiente', 'urgencia' => $urgency]);
            if ($assetIsOfficial) $alert->activos()->attach($entry['asset_id']);
        }

        foreach ($data['maintenances'] as $entry) {
            if (! in_array($entry['asset_id'], $officialAssetIds, true)) continue;
            $technician = User::find($entry['technician_id']);
            Maintenance::create(['id' => $entry['id'], 'activo_id' => $entry['asset_id'], 'fecha_mantenimiento' => $entry['maintenance_date'], 'descripcion' => $entry['detail'], 'observaciones' => 'Técnico histórico: '.($technician?->nombre ?? 'No identificado').'. Próxima fecha histórica: '.$entry['next_date'].'.']);
        }

        $assetsByCode = Asset::all()->keyBy(fn (Asset $asset) => $asset->codigo);
        foreach ($data['calendarTasks'] ?? [] as $entry) {
            $technician = $usersByName->get($entry['technician'] ?? '');
            $asset = $assetsByCode->get($entry['asset'] ?? '');
            CalendarTask::create(['id' => $entry['id'], 'fecha' => $entry['date'], 'hora' => $entry['time'] ?? '09:00', 'titulo' => $entry['title'], 'tipo' => $entry['type'] ?? 'mantenimiento', 'category' => $entry['category'] ?? 'Mantenimiento', 'technician_id' => $technician?->id, 'technician_name' => $entry['technician'] ?? null, 'asset_id' => $asset?->id, 'asset_code' => $entry['asset'] ?? null, 'priority' => $entry['priority'] ?? 'Media', 'completed' => ! empty($entry['completed'])]);
        }

        foreach ($data['auditLogs'] ?? [] as $entry) Audit::create(['fecha_auditoria' => $entry['timestamp'] ?? now(), 'descripcion' => "{$entry['action']} — {$entry['entity']}: {$entry['detail']}"]);
    }
}
