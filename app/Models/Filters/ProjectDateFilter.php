<?php

namespace App\Models\Filters;

use Illuminate\Database\Eloquent\Builder;
use Lacodix\LaravelModelFilter\Filters\Filter;

class ProjectDateFilter extends Filter
{
    public function __construct(protected string $field) {}

    public function apply(Builder $query): Builder
    {
        if (is_array($this->values) && count($this->values) === 2) {
            if ($this->isDateRange($this->values)) {
                return $query->whereBetween($this->field, [$this->values[0], $this->values[1]]);
            }
        }

        return $query->whereIn($this->field, $this->values);
    }

    // Método para verificar si los valores son un rango de fechas válido
    private function isDateRange($values): bool
    {
        return isset($values[0], $values[1]) &&
               \Carbon\Carbon::parse($values[0])->isValid() &&
               \Carbon\Carbon::parse($values[1])->isValid();
    }
}
