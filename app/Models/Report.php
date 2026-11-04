<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Report extends Model
{
    protected $table = 'reportes';

    protected $fillable = [
        'jefe_id', 'auditoria_id', 'historial', 'estado_reporte', 'urgencia',
        'descripcion', 'fecha_creacion', 'fecha_cierre', 'resolucion',
        'informe_accionar', 'diagnostico_final',
    ];

    protected $hidden = ['historial'];

    protected function casts(): array
    {
        return ['fecha_creacion' => 'datetime', 'fecha_cierre' => 'datetime'];
    }

    public function activos(): BelongsToMany
    {
        return $this->belongsToMany(Asset::class, 'reporte_activo', 'reporte_id', 'activo_id');
    }

    public function creadores(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'usuario_reporte', 'reporte_id', 'usuario_id');
    }

    public function jefe(): BelongsTo
    {
        return $this->belongsTo(TechnicalChief::class, 'jefe_id');
    }

    public function auditoria(): BelongsTo
    {
        return $this->belongsTo(Audit::class, 'auditoria_id');
    }

    public function getActivoAttribute(): ?Asset
    {
        return $this->relationLoaded('activos') ? $this->activos->first() : $this->activos()->first();
    }

    public function getCreadorAttribute(): ?User
    {
        return $this->relationLoaded('creadores') ? $this->creadores->first() : $this->creadores()->first();
    }

    public function getActivoIdAttribute(): ?int
    {
        return $this->activo?->id;
    }

    public function getCreadorIdAttribute(): ?int
    {
        return $this->creador?->id;
    }

    public function getCodigoAttribute(): string
    {
        $date = $this->fecha_creacion ?? $this->created_at;
        $year = $date ? $date->format('Y') : now()->format('Y');

        return 'REP-'.$year.'-'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    public function getPrioridadAttribute(): string
    {
        return $this->urgencia === 'Critico' ? 'Critica' : $this->urgencia;
    }
}
