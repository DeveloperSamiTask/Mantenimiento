<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Lacodix\LaravelModelFilter\Traits\IsSearchable;
use Lacodix\LaravelModelFilter\Traits\IsSortable;
use LaravelArchivable\Archivable;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/*Es un modelo que representa una tabla de plantillas de tareas. */
class CheckList extends Model implements AuditableContract
{
    use Archivable, Auditable, HasFactory, IsSearchable, IsSortable;

    protected $fillable = ['name', 'period_id', 'game_id', 'archive', 'type'];
    protected $searchable = ['name'];
    protected $sortable = ['name' => 'asc',];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
    }

    public function typeCheck(): BelongsTo
    {
        return $this->belongsTo(TypeCheck::class, 'type');
    }

}
