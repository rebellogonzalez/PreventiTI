<?php

namespace App\Services;

use App\Models\CalendarTask;
use App\Models\Maintenance;
use App\Models\Report;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class CostStatisticsService
{
    public function summarize(): array
    {
        $today = CarbonImmutable::today('America/Montevideo');
        $budget = DB::table('maintenance_budgets')->where('year', $today->year)->first();
        $costs = DB::table('maintenance_costs')->whereBetween('incurred_on', [$today->startOfYear()->toDateString(), $today->toDateString()])->get();
        $currency = $budget?->currency ?? 'UYU';
        $expenses = $costs->where('currency', $currency);
        $executed = $expenses->sum(fn ($row) => $row->quantity * $row->unit_cost_minor) / 100;
        $allocated = $budget ? $budget->amount_minor / 100 : null;
        $components = $costs->whereIn('kind', ['part', 'supply'])
            ->groupBy(fn ($row) => json_encode([$row->name, $row->currency]))
            ->map(function ($rows) {
                $quantity = $rows->sum('quantity');
                $total = $rows->sum(fn ($row) => $row->quantity * $row->unit_cost_minor) / 100;
                return ['id' => $rows->first()->id, 'name' => $rows->first()->name, 'category' => $rows->pluck('category')->unique()->implode(' / '),
                    'currency' => $rows->first()->currency, 'count' => $quantity, 'cost' => $quantity ? $total / $quantity : 0,
                    'total' => $total, 'reason' => $rows->first()->reason];
            })->sortByDesc('count')->values();

        $reports = Report::with('activos')->whereBetween('fecha_creacion', [$today->startOfYear()->toDateString(), $today->endOfDay()->utc()->toDateTimeString()])->get();
        $recurring = $reports->groupBy('descripcion')->filter(fn ($rows) => $rows->count() > 1)
            ->map(function ($rows) {
                $diagnosed = $rows->whereNotNull('diagnostico_final')->sortByDesc('updated_at')->first();
                $pending = $rows->where('estado_reporte', '!=', 'Finalizado')->count();
                return ['id' => $rows->first()->id, 'title' => $rows->first()->descripcion,
                    'frequency' => $rows->count().' reportes en el año',
                    'affected_devices' => $rows->flatMap(fn ($report) => $report->activos->map->codigo)->unique()->implode(', '),
                    'root_cause' => $diagnosed?->diagnostico_final ?? 'Pendiente de diagnóstico documentado.',
                    'countermeasure' => $diagnosed?->informe_accionar ?? 'Pendiente de intervención documentada.',
                    'status' => $pending ? $pending.' reportes en seguimiento' : 'Reportes finalizados'];
            })->values();

        $months = collect(range(-2, 3))->map(function ($offset) use ($today) {
            $start = $today->startOfMonth()->addMonths($offset);
            $range = [$start->toDateString(), $start->endOfMonth()->toDateString()];
            return ['label' => $start->locale('es')->translatedFormat('M Y'),
                'completed' => Maintenance::whereBetween('fecha_mantenimiento', $range)->count(),
                'scheduled' => CalendarTask::where('tipo', 'mantenimiento')->where('completed', false)->whereBetween('fecha', $range)->count()];
        });

        return [
            'budget' => ['currency' => $currency, 'year' => $today->year, 'annual_budget' => $allocated,
                'executed_to_date' => $executed, 'remaining_budget' => $allocated === null ? null : $allocated - $executed,
                'execution_percent' => $allocated ? round($executed / $allocated * 100, 1) : 0,
                'scope' => $budget?->scope ?? 'Sin presupuesto registrado para este ejercicio.',
                'estimated_savings_preventive' => null,
                'breakdown' => $expenses->groupBy('kind')->map(fn ($rows, $kind) => [
                    'item' => ['part' => 'Repuestos', 'supply' => 'Insumos', 'service' => 'Servicios externos'][$kind] ?? $kind,
                    'amount' => $rows->sum(fn ($row) => $row->quantity * $row->unit_cost_minor) / 100,
                ])->values()],
            'components_replaced' => $components,
            'recurring_incidents' => $recurring,
            'maintenance_months' => $months,
        ];
    }
}
