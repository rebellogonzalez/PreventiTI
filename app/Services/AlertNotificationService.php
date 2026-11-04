<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\Report;

class AlertNotificationService
{
    /** Creates the Hito 1 alert linked to every asset documented by a report. */
    public function notifyNewReport(Report $report): Alert
    {
        $alert = Alert::create([
            'reporte_id' => $report->id,
            'nivel_alerta' => $report->urgencia,
            'tipo_alerta' => 'Reporte',
            'fecha_generacion' => now(),
            'estado_alerta' => 'Pendiente',
            'urgencia' => $report->urgencia,
        ]);

        $alert->activos()->sync($report->activos()->pluck('activos.id'));

        return $alert;
    }
}
