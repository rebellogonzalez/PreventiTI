<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TechnicalChiefName extends Model
{
    protected $table = 'jefe_nombres';

    protected $primaryKey = 'jefe_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['jefe_id', 'nombre', 'apellido'];
}
