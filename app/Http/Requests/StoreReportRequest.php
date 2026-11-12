<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'submission_id' => 'sometimes|uuid',
            'asset_id' => 'required|integer|min:1',
            'priority' => 'required|in:Baja,Media,Alta,Critica',
            'description' => 'required|string|min:5|max:2000',
        ];
    }

    public function messages(): array
    {
        return [
            'asset_id.required' => 'Debe seleccionar el activo afectado.',
            'asset_id.integer' => 'Seleccione un dispositivo válido.',
            'asset_id.min' => 'Seleccione un dispositivo válido.',
            'priority.required' => 'La prioridad es obligatoria.',
            'priority.in' => 'La prioridad debe ser Baja, Media, Alta o Crítica.',
            'description.required' => 'Debe detallar la situación observada.',
            'description.min' => 'La descripción debe tener al menos 5 caracteres.',
        ];
    }
}
