<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Explicit ownership: no truncation, ID reset, or inference from a person's name. */
class SimulationSnapshot
{
    public const TABLES = ['maintenance_costs', 'alertas', 'mantenimientos', 'tareas_calendario', 'reportes', 'maintenance_budgets', 'auditorias'];
    public const PROTECTED = ['usuarios', 'usuario_nombres', 'usuario_direcciones', 'tecnicos', 'jefes_tecnicos', 'jefe_nombres', 'activos', 'categorias_activo', 'activo_ubicaciones', 'asset_map_positions'];

    public function record(string $table, int $id): array
    {
        if (! in_array($table, [...self::TABLES, 'consumibles'], true)) throw new RuntimeException('Tabla no autorizada en el manifiesto.');
        $row = DB::table($table)->where('id', $id)->first();
        if (! $row) throw new RuntimeException("Falta {$table} #{$id}; se conserva la base sin cambios.");
        $data = ['row' => (array) $row];
        $relations = match ($table) {
            'reportes' => ['reporte_activo' => 'reporte_id', 'usuario_reporte' => 'reporte_id', 'tecnico_jefe_tecnico' => 'reporte_id', 'mantenimientos' => 'reporte_id', 'alertas' => 'reporte_id'],
            'alertas' => ['activo_alerta' => 'alerta_id'],
            'mantenimientos' => ['maintenance_costs' => 'maintenance_id'],
            default => [],
        };
        foreach ($relations as $related => $key) $data[$related] = $this->sorted(DB::table($related)->where($key, $id)->get()->all());
        return ['table' => $table, 'id' => $id, 'hash' => hash('sha256', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR))];
    }

    public function assertUnchanged(array $manifest): void
    {
        foreach ($manifest as $entry) {
            if ($this->record($entry['table'], (int) $entry['id'])['hash'] !== $entry['hash']) {
                throw new RuntimeException("{$entry['table']} #{$entry['id']} cambió desde su carga. No se reemplaza ningún registro.");
            }
        }
    }

    public function ids(): array
    {
        $result = [];
        foreach ([...self::TABLES, 'consumibles'] as $table) $result[$table] = DB::table($table)->pluck('id')->all();
        return $result;
    }

    public function addedSince(array $before): array
    {
        $result = [];
        foreach ($this->ids() as $table => $ids) {
            foreach (array_diff($ids, $before[$table]) as $id) $result[] = $this->record($table, $id);
        }
        return $result;
    }

    public function deleteOwned(array $manifest): void
    {
        // Validate the entire graph before deleting its first row.
        $this->assertUnchanged($manifest);
        foreach ([...self::TABLES, 'consumibles'] as $table) {
            $ids = array_column(array_filter($manifest, fn ($entry) => $entry['table'] === $table), 'id');
            if ($ids) DB::table($table)->whereIn('id', $ids)->delete();
        }
    }

    public function protectedFingerprint(): string
    {
        $rows = [];
        foreach (self::PROTECTED as $table) $rows[$table] = $this->sorted(DB::table($table)->get()->all());
        return hash('sha256', json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    private function sorted(array $rows): array
    {
        $rows = array_map(fn ($row) => json_encode($row, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), $rows);
        sort($rows, SORT_STRING);
        return $rows;
    }
}
