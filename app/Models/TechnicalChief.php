<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TechnicalChief extends Model
{
    protected $table = 'jefes_tecnicos';

    public $timestamps = false;

    protected $fillable = ['id', 'nombre_completo'];

    public static function firstActiveId(): ?int
    {
        return static::join('usuarios', 'usuarios.id', '=', 'jefes_tecnicos.id')
            ->where('usuarios.activo', true)->orderBy('jefes_tecnicos.id')->sharedLock()->value('jefes_tecnicos.id');
    }
}
