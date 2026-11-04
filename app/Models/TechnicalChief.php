<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TechnicalChief extends Model
{
    protected $table = 'jefes_tecnicos';

    public $timestamps = false;

    protected $fillable = ['id', 'nombre_completo'];
}
