<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Maintenance extends Model
{
    protected $table = 'mantenimientos';

    protected $appends = ['proxima_fecha', 'periodicity_days'];

    protected $fillable = ['activo_id', 'reporte_id', 'fecha_mantenimiento', 'descripcion', 'observaciones'];

    protected function casts(): array
    {
        return ['fecha_mantenimiento' => 'date:Y-m-d'];
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

    public function getProximaFechaAttribute(): ?Carbon
    {
        return $this->activo && $this->activo->frecuencia_service
            ? Carbon::parse($this->fecha_mantenimiento)->addDays($this->activo->frecuencia_service)
            : null;
    }

    public function getPeriodicityDaysAttribute(): ?int
    {
        return $this->activo?->frecuencia_service;
    }
}
