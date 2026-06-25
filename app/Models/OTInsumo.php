<?php

namespace App\Models;

use App\Models\Filters\WhereInFilter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Lacodix\LaravelModelFilter\Traits\HasFilters;
use App\Models\Insumo;

class OTInsumo extends Model {
    protected $table = 'ot_insumos';
    protected $fillable = ['ot_id', 'period_id', 'game_id', 'user_id', 'due_on', 'name'];

    public function insumos() {
        return $this->hasMany(Insumo::class, 'ot_insumos_id');
    }
}
