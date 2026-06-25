<?php

namespace App\Models;

use App\Models\Filters\WhereInFilter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Lacodix\LaravelModelFilter\Traits\HasFilters;

class Insumo extends Model {
    protected $table = 'insumos';
    protected $fillable = ['ot_insumos_id', 'cod_producto', 'name', 'unidad', 'almacen', 'cantidad'];
}
