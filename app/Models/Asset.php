<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Asset extends Model
{
    protected $table = 'activos';

    protected $fillable = [
        'categoria_id', 'serial', 'ubicacion', 'descripcion', 'estado_activo',
        'fecha_alta', 'frecuencia_service',
    ];

    protected function casts(): array
    {
        return ['fecha_alta' => 'date:Y-m-d', 'frecuencia_service' => 'integer'];
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'categoria_id');
    }

    public function ubicacionDetalle(): HasOne
    {
        return $this->hasOne(Location::class, 'activo_id');
    }

    public function reportes(): BelongsToMany
    {
        return $this->belongsToMany(Report::class, 'reporte_activo', 'activo_id', 'reporte_id');
    }

    public function mantenimientos(): HasMany
    {
        return $this->hasMany(Maintenance::class, 'activo_id')->orderByDesc('fecha_mantenimiento');
    }

    public function consumible(): HasOne
    {
        return $this->hasOne(AssetConsumable::class, 'activo_id');
    }

    public function consumable(): HasOne
    {
        return $this->consumible();
    }

    public function consumibles(): HasMany
    {
        return $this->hasMany(AssetConsumable::class, 'activo_id');
    }

    public function alertas(): BelongsToMany
    {
        return $this->belongsToMany(Alert::class, 'activo_alerta', 'activo_id', 'alerta_id');
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->whereNotIn('estado_activo', ['Fuera de Servicio', 'Retirado']);
    }

    public function getDaysToMaintenanceAttribute(): ?int
    {
        if (! $this->next_maintenance) {
            return null;
        }

        return (int) Carbon::today()->diffInDays($this->next_maintenance->copy()->startOfDay(), false);
    }

    public function getStatusAlertAttribute(): string
    {
        if (! $this->activo || strtolower((string) $this->estado) === 'fuera de servicio') {
            return 'critica';
        }

        $days = $this->days_to_maintenance;
        if ($days !== null && $days < 0) {
            return 'critica';
        }

        if ($this->consumible && $this->consumible->nivel_actual <= 20) {
            return 'atencion';
        }

        return $days !== null && $days <= 7 ? 'atencion' : 'normal';
    }

    public function getCodigoAttribute(): string
    {
        if ($details = $this->inventoryDetails()) {
            return $details['codigo'] ?? 'ACT-'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
        }

        if (preg_match('/C[oó]digo legado:\s*([^.;]+)/iu', (string) $this->descripcion, $matches)) {
            return trim($matches[1]);
        }

        return 'ACT-'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    public function getSerieAttribute(): ?string
    {
        return $this->serial;
    }

    public function getNombreAttribute(): string
    {
        if ($details = $this->inventoryDetails()) {
            return $details['nombre'];
        }

        if (preg_match('/Nombre legado:\s*(.*?)\.\s*Responsable legado:/su', (string) $this->descripcion, $matches)) {
            return trim($matches[1]);
        }

        if (preg_match('/Nombre legado:\s*([^.;]+)/iu', (string) $this->descripcion, $matches)) {
            return trim($matches[1]);
        }

        return $this->descripcion;
    }

    public function getResponsableAttribute(): string
    {
        if ($details = $this->inventoryDetails()) {
            return $details['responsable'];
        }

        if (preg_match('/Responsable legado:\s*([^.;]+)/iu', (string) $this->descripcion, $matches)) {
            return trim($matches[1]);
        }

        return 'No asignado';
    }

    /**
     * El modelo Hito 1 conserva estos datos en descripcion. El formato versionado
     * evita truncar puntos, comillas o etiquetas escritas dentro de los valores.
     * La descripción original y el código patrimonial se conservan al editar.
     */
    public function setInventoryDetails(string $name, ?string $responsible): void
    {
        $details = $this->inventoryDetails();
        $this->descripcion = json_encode([
            '_format' => 'preventiti.asset.v1',
            'codigo' => $this->exists ? $this->codigo : null,
            'nombre' => $name,
            'responsable' => $responsible !== null && $responsible !== '' ? $responsible : 'No asignado',
            'descripcion_original' => $details['descripcion_original'] ?? (string) $this->descripcion,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private function inventoryDetails(): ?array
    {
        $details = json_decode((string) $this->descripcion, true);

        return is_array($details)
            && ($details['_format'] ?? null) === 'preventiti.asset.v1'
            && is_string($details['nombre'] ?? null)
            && is_string($details['responsable'] ?? null)
                ? $details : null;
    }

    public function getEstadoAttribute(): string
    {
        return $this->estado_activo;
    }

    public function setEstadoAttribute(string $value): void
    {
        $this->attributes['estado_activo'] = $value;
    }

    public function getActivoAttribute(): bool
    {
        return ! in_array(strtolower((string) $this->estado_activo), ['fuera de servicio', 'retirado'], true);
    }

    /** La disponibilidad operativa no determina si hubo una baja patrimonial. */
    public function getRetiradoAttribute(): bool
    {
        return mb_strtolower(trim((string) $this->estado_activo)) === 'retirado';
    }

    public function getPeriodicidadDiasAttribute(): int
    {
        return (int) $this->frecuencia_service;
    }

    public function getLastMaintenanceAttribute(): ?Carbon
    {
        $date = $this->relationLoaded('mantenimientos')
            ? $this->mantenimientos->max('fecha_mantenimiento')
            : $this->mantenimientos()->max('fecha_mantenimiento');

        return $date ? Carbon::parse($date) : null;
    }

    public function getNextMaintenanceAttribute(): ?Carbon
    {
        return $this->last_maintenance && $this->frecuencia_service
            ? $this->last_maintenance->copy()->addDays($this->frecuencia_service)
            : null;
    }
}
