<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Alert extends Model
{
    protected $table = 'alertas';

    protected $fillable = [
        'reporte_id',
        'nivel_alerta',
        'tipo_alerta',
        'fecha_generacion',
        'estado_alerta',
        'urgencia',
    ];

    protected function casts(): array
    {
        return ['fecha_generacion' => 'datetime'];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class, 'reporte_id');
    }

    public function activos(): BelongsToMany
    {
        return $this->belongsToMany(Asset::class, 'activo_alerta', 'alerta_id', 'activo_id');
    }
}
