<?php

namespace App\Services;

use App\Models\Report;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class ReportReadService
{
    public const RELATIONS = ['activos.categoria', 'creadores.nombres', 'creadores.chief', 'creadores.technician', 'assignments.nombres', 'assignments.technician'];

    public function query(User $user): Builder
    {
        return Report::visibleTo($user)->with(self::RELATIONS);
    }

    public function record(Report $report, User $viewer, bool $summary = false): array
    {
        $report->loadMissing(self::RELATIONS);
        $closed = $report->isClosed();
        $assigned = $closed ? null : $report->assignments->first(fn ($person) => $person->pivot->fecha_finalizacion === null);
        $last = $closed ? $report->assignments->sortByDesc(fn ($person) => [
            $person->pivot->fecha_asignacion, $person->pivot->motivo_reasignacion === null, $person->pivot->fecha_finalizacion,
        ])->first() : null;
        $technician = fn ($person) => $person ? [
            'id' => $person->id, 'name' => $person->nombre, 'specialty' => $person->especialidad,
            'assigned_at' => self::localDate($person->pivot->fecha_asignacion),
        ] : null;
        $data = [
            'id' => $report->id, 'code' => $report->codigo,
            'asset_id' => $report->activo_id, 'asset_code' => $report->activo?->codigo,
            'asset_name' => $report->activo?->nombre, 'asset_category' => $report->activo?->categoria?->nombre,
            'creator_id' => $report->creador_id, 'creator_name' => $report->creador?->nombre,
            'creator_role' => $report->creador?->role_title, 'priority' => $report->prioridad,
            'description' => $report->descripcion, 'status' => $report->estado_reporte, 'closed' => $closed,
            'version' => (int) $report->revision_flujo,
            'can_update_status' => $assigned !== null && $viewer->can('transition', [$report, $assigned->id]),
            'assigned_technician' => $technician($assigned), 'last_technician' => $technician($last),
            'closed_at' => self::localDate($report->fecha_cierre), 'created_at' => self::localDate($report->fecha_creacion),
            'updated_at' => self::localDate($report->updated_at),
        ];
        if ($summary) return $data;
        return $data + [
            'action_report' => $report->informe_accionar ?: $report->resolucion,
            'final_diagnosis' => $report->diagnostico_final,
            'interventions' => $report->intervenciones ?? [],
            'assignment_history' => $report->assignments->map(fn ($person) => [
                'technician_id' => $person->id, 'technician_name' => $person->nombre,
                'assigned_at' => self::localDate($person->pivot->fecha_asignacion),
                'finished_at' => self::localDate($person->pivot->fecha_finalizacion),
                'reassignment_reason' => $person->pivot->motivo_reasignacion,
            ])->values()->all(),
        ];
    }

    public static function localDate($utc): ?string
    {
        return $utc ? CarbonImmutable::parse($utc, 'UTC')->setTimezone(Report::TIME_ZONE)->format('Y-m-d H:i:s') : null;
    }

    public function history(User $user, array $filters): array
    {
        $size = 20;
        $page = (int) ($filters['page'] ?? 1);
        $query = $this->query($user);
        if (!empty($filters['status'])) $query->where('estado_reporte', $filters['status']);
        if (!empty($filters['asset'])) {
            if (str_starts_with($filters['asset'], 'id:')) {
                $query->whereHas('activos', fn ($q) => $q->whereKey((int) substr($filters['asset'], 3)));
            } else {
                $query->doesntHave('activos');
            }
        }
        foreach (['from', 'to'] as $key) {
            if (empty($filters[$key])) continue;
            $boundary = CarbonImmutable::parse($filters[$key], Report::TIME_ZONE)->startOfDay();
            $query->where('fecha_creacion', $key === 'from' ? '>=' : '<', ($key === 'from' ? $boundary : $boundary->addDay())->utc());
        }
        $direction = ($filters['sort'] ?? '') === 'oldest' ? 'asc' : 'desc';
        $query->orderByRaw('CASE WHEN fecha_creacion IS NULL THEN 1 ELSE 0 END')->orderBy('fecha_creacion', $direction)->orderBy('id', $direction);
        $terms = preg_split('/\s+/u', Str::lower(Str::ascii(trim($filters['query'] ?? ''))), flags: PREG_SPLIT_NO_EMPTY);
        $technician = $filters['technician'] ?? '';
        if (!$terms && $technician === '') {
            $total = $query->count();
            $page = min($page, max(1, (int) ceil($total / $size)));
            $data = $query->forPage($page, $size)->get()->map(fn ($r) => $this->record($r, $user))->all();
        } else {
            // Names, derived asset codes and historic notes have the same search semantics on SQLite and MariaDB.
            $total = 0;
            $data = [];
            foreach ($query->lazy(100) as $model) {
                $r = $this->record($model, $user);
                $person = $r['assigned_technician'] ?? $r['last_technician'];
                if ($technician !== '' && (string) ($person['id'] ?? 'unassigned') !== $technician) continue;
                if ($terms) {
                    $values = [$r['id'], $r['code'], $r['asset_id'], $r['asset_code'], $r['asset_name'], $r['description'],
                        $r['action_report'], $r['final_diagnosis'], $r['status'], $r['creator_name'], $r['created_at'], $person['id'] ?? '', $person['name'] ?? '',
                        ...array_column($r['assignment_history'], 'technician_name'), ...array_column($r['interventions'], 'text')];
                    $text = Str::lower(Str::ascii(implode(' ', $values)));
                    if (!Str::containsAll($text, $terms)) continue;
                }
                if (intdiv($total, $size) === $page - 1) $data[] = $r;
                $total++;
            }
        }
        return ['success' => true, 'data' => $data, 'total' => $total, 'page' => $page];
    }
}
