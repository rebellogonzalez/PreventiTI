<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Maintenance extends Model
{
    protected $table = 'mantenimientos';

    protected $appends = ['proxima_fecha', 'periodicity_days'];

    protected $fillable = ['activo_id', 'reporte_id', 'fecha_mantenimiento', 'descripcion', 'observaciones', 'task_id', 'technician_id', 'technician_name',
        'interval_days', 'projected_date', 'submission_key', 'submission_hash'];

    protected $hidden = ['submission_key', 'submission_hash'];

    protected function casts(): array
    {
        return ['fecha_mantenimiento' => 'date:Y-m-d', 'projected_date' => 'date:Y-m-d', 'interval_days' => 'integer', 'requires_review' => 'boolean'];
    }

    public function activo(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'activo_id');
    }

    public function reporte(): BelongsTo
    {
        return $this->belongsTo(Report::class, 'reporte_id');
    }

    public function getDetalleAttribute(): string
    {
        return $this->descripcion;
    }

    public static function projectNextDate(Carbon $date, int $intervalDays): ?Carbon
    {
        return $intervalDays > 0 ? $date->copy()->addDays($intervalDays) : null;
    }

    public function getProximaFechaAttribute(): ?Carbon
    {
        if ($this->interval_days === 0) return null;
        if ($this->projected_date) return $this->projected_date;
        if (preg_match('/Próxima (?:revisión calculada|fecha histórica): (\d{4})-(\d{2})-(\d{2})/u', (string) $this->observaciones, $match)
            && checkdate((int) $match[2], (int) $match[3], (int) $match[1])) {
            return Carbon::createFromDate((int) $match[1], (int) $match[2], (int) $match[3])->startOfDay();
        }
        return null;
    }

    public function getPeriodicityDaysAttribute(): ?int
    {
        if ($this->interval_days !== null) return (int) $this->interval_days;
        $next = $this->proxima_fecha;
        return $next && $next->gt($this->fecha_mantenimiento)
            ? (int) $this->fecha_mantenimiento->diffInDays($next, false) : null;
    }

    public function recordedTechnician(): ?string
    {
        if ($this->technician_name) return $this->technician_name;
        if (preg_match('/^(?:Registrado por |Técnico (?:legado|histórico): )(.+?)\. Próxima /u', (string) $this->observaciones, $match)) {
            return $match[1].' (registro histórico)';
        }
        return null;
    }
}
