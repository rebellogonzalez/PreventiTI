<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssetCategory extends Model
{
    protected $table = 'categorias_activo';

    protected $fillable = ['nombre', 'descripcion', 'requiere_consumible'];

    protected function casts(): array
    {
        return ['requiere_consumible' => 'boolean'];
    }

    public function activos(): HasMany
    {
        return $this->hasMany(Asset::class, 'categoria_id');
    }
}
