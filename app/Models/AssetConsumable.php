<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetConsumable extends Model
{
    protected $table = 'consumibles';

    protected $fillable = ['activo_id', 'nombre', 'tipo', 'nivel'];

    protected function casts(): array
    {
        return ['nivel' => 'float'];
    }

    public function activo(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'activo_id');
    }

    public function getNivelActualAttribute(): float
    {
        return (float) $this->nivel;
    }

    public function setNivelActualAttribute(float $value): void
    {
        $this->attributes['nivel'] = $value;
    }

    public function getNivelMinimoAttribute(): float
    {
        return 20.0;
    }
}
