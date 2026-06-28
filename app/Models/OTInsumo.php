<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OTInsumo extends Model
{
    protected $table = 'ot_insumos';

    protected $fillable = ['ot_id', 'period_id', 'game_id', 'user_id', 'due_on', 'name'];

    public function insumos()
    {
        return $this->hasMany(Insumo::class, 'ot_insumos_id');
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }
}
