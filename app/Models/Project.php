<?php

namespace App\Models;

use App\Models\Filters\IsNullFilter;
use App\Models\Filters\ProjectCompletedFilter;
use App\Models\Filters\ProjectDateFilter;
use App\Models\Filters\ProjectFaultDateFilter;
use App\Models\Filters\ProjectOverdueFilter;
use App\Models\Filters\WhereHasFilter;
use App\Models\Filters\WhereInFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Lacodix\LaravelModelFilter\Traits\HasFilters;
use Lacodix\LaravelModelFilter\Traits\IsSearchable;
use LaravelArchivable\Archivable;
use Overtrue\LaravelFavorite\Traits\Favoriteable;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;


class Project extends Model implements AuditableContract, Sortable
{
    use Archivable, Auditable, Favoriteable, IsSearchable, HasFactory, HasFilters, SortableTrait;

    protected $fillable = [
        'client_company_id',
        'group_id',
        'game_id',
        'period_id',
        'type_id',
        'user_generate',
        'user_review',
        'user_finalize',
        'name',
        'number',
        'description',
        'rate',
        'due_on',
        'fault_date',
        'start_date',
        'estimation',
        'order_column',
        'default',
        'motive_archived',
        'completed_at',
    ];

    protected $searchable = [
        'name',
        'id',
        'number',
    ];

    protected $casts = [
        'due_on' => 'date',
        'start_date' => 'datetime',
        'fault_date' => 'datetime',
        'completed_at' => 'datetime',
        'estimation' => 'float',
    ];

    protected $observables = [
        'archived',
        'unArchived',
    ];

    public array $defaultWith = [
        'clientCompany:id,name',
        'users:id,name,signature',
        'userGenerate:id,name,signature',
        'userReview:id,name,signature',
        'userFinalize:id,name,signature',
        'game:id,name',
        'type:id,name',
        'period:id,name',
        'labels:id,name,color',
        'timeLogs.user:id,name',
    ];

    public function filters(): array
    {
        return [
            (new WhereInFilter('group_id'))->setQueryName('groups'),
            (new WhereInFilter('period_id'))->setQueryName('periods'),
            (new ProjectDateFilter('created_at'))->setQueryName('date'),
            (new WhereInFilter('project_user_access'))->setQueryName('assignees'),
            (new ProjectOverdueFilter('due_on'))->setQueryName('overdue'),
            (new IsNullFilter('due_on'))->setQueryName('not_set'),
            (new ProjectCompletedFilter('completed_at'))->setQueryName('status'),
            (new ProjectFaultDateFilter('fault_date'))->setQueryName('fault_date'),
            (new WhereHasFilter('labels'))->setQueryName('labels'),
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('ordered', function ($query) {
            $query->ordered();
        });
    }

    public function scopeWithDefault(Builder $query)
    {
        $query->with($this->defaultWith);
    }

    public function loadDefault()
    {
        return $this->load($this->defaultWith);
    }

    public function clientCompany(): BelongsTo
    {
        return $this->belongsTo(ClientCompany::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_user_access');
    }

    public function projectGroup(): BelongsTo
    {
        return $this->belongsTo(ProjectGroup::class, 'group_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function taskGroups(): HasMany
    {
        return $this->hasMany(TaskGroup::class, 'project_id');
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(ProjectType::class, 'type_id');
    }

    public function userGenerate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_generate');
    }

    public function userReview(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_review');
    }

    public function userFinalize(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_finalize');
    }

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(Label::class);
    }

    public function timeLogs(): HasMany
    {
        return $this->hasMany(TimeLog::class);
    }

    public function favoritedByAuthUser(): BelongsToMany
    {
        return $this->belongsToMany(
            config('auth.providers.users.model'),
            config('favorite.favorites_table'),
            'favoriteable_id',
            config('favorite.user_foreign_key')
        )
            ->where('favoriteable_type', $this->getMorphClass())
            ->where('user_id', auth()->id());
    }

    public function checkLists()
    {
        return $this->hasMany(CheckList::class, 'game_id', 'game_id');
    }

    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'activity_capable');
    }

    public static function dropdownValues(): array
    {
        return self::orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($i) => ['value' => (string) $i->id, 'label' => $i->name])
            ->toArray();
    }
}
