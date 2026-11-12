<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;

class Report extends Model
{
    protected $table = 'reportes';

    protected $fillable = [
        'jefe_id', 'auditoria_id', 'historial', 'estado_reporte', 'urgencia',
        'descripcion', 'fecha_creacion', 'fecha_cierre', 'resolucion',
        'informe_accionar', 'diagnostico_final',
    ];

    protected $hidden = ['historial', 'clave_envio', 'intervenciones'];

    public const CLOSED_STATES = ['Finalizado', 'Resuelto', 'Cerrado', 'Cancelado'];
    public const TIME_ZONE = 'America/Montevideo';

    public function isClosed(): bool
    {
        return $this->fecha_cierre !== null || in_array($this->estado_reporte, self::CLOSED_STATES, true);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasHierarchyAtLeast(2)) return $query;
        return $query->where(function ($q) use ($user) {
            $q->whereHas('creadores', fn ($q) => $q->whereKey($user->id));
            if ($user->isTechnician()) {
                $q->orWhereIn('reportes.id', DB::table('tecnico_jefe_tecnico')->select('reporte_id')->where('tecnico_id', $user->id));
            }
        });
    }

    public static function revisionFor(User $user): string
    {
        $reports = static::visibleTo($user)->selectRaw('COUNT(*) AS total, COALESCE(SUM(revision_flujo), 0) AS revision, MAX(id) AS latest')->first();
        $unread = $user->isLeader() ? Alert::where('estado_alerta', '!=', 'Leída')->count() : 0;
        return hash('sha256', implode('/', [$reports->total, $reports->revision, $reports->latest, $unread]));
    }

    public function assignments(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tecnico_jefe_tecnico', 'reporte_id', 'tecnico_id')
            ->withPivot('fecha_asignacion', 'fecha_finalizacion', 'motivo_reasignacion')
            ->orderByPivot('fecha_asignacion')->orderByPivot('fecha_finalizacion', 'desc');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('fecha_cierre')->whereNotIn('estado_reporte', self::CLOSED_STATES);
    }

    protected function casts(): array
    {
        return ['fecha_creacion' => 'datetime', 'fecha_cierre' => 'datetime', 'intervenciones' => 'array', 'revision_flujo' => 'integer'];
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
        return match ($this->urgencia) { 'Bajo' => 'Baja', 'Medio' => 'Media', 'Alto' => 'Alta', 'Critico', 'Crítica' => 'Critica', default => $this->urgencia };
    }
}
