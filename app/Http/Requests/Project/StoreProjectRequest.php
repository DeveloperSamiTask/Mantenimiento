<?php

namespace App\Http\Requests\Project;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', Rule::unique('projects', 'name')],
            'description' => ['string', 'nullable'],
            'client_company_id' => ['nullable'],
            'game_id' => ['nullable', 'exists:games,id'],
            'type_id' => ['nullable', 'exists:project_types,id'],
            'group_id' => ['nullable', 'exists:project_groups,id'],
            'period_id' => ['nullable', 'exists:periods,id'],
            'due_on' => ['nullable'],
            'fault_date' => ['nullable'],
            'start_date' => ['nullable'],
            'estimation' => ['nullable'],
            'rate' => ['nullable'],
            'default' => ['boolean'],
            'labels' => ['array'],
            'users' => ['array'],
            'tasks' => ['array'],

            'nameOT' => ['nullable', 'string'],
            'insumos' => ['nullable', 'array'],
            'insumos.*.cod_producto' => ['required_with:insumos', 'string'],
            'insumos.*.name' => ['required_with:insumos', 'string'],
            'insumos.*.almacen' => ['required_with:insumos', 'string'],
            'insumos.*.unidad' => ['nullable', 'string'],
            'insumos.*.cantidad' => ['required_with:insumos', 'numeric', 'min:1'],
        ];
    }
}
