<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMaintenanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return $user && ($user->isAdmin() || $user->isTechnician());
    }

    public function rules(): array
    {
        return [
            'asset_id' => 'required|exists:activos,id',
            'maintenance_date' => 'required|date_format:Y-m-d|before_or_equal:'.now('America/Montevideo')->toDateString(),
            'submission_id' => 'nullable|uuid',
            'task_id' => 'nullable|integer|min:1',
            'task_version' => 'nullable|integer|min:0',
            'report_id' => 'nullable|integer|min:1|prohibits:finding',
            'finding' => 'nullable|string|min:5|max:2000',
            'priority' => 'required_with:finding|in:Baja,Media,Alta,Critica',
            'detail' => 'required|string|min:5|max:2000',
        ];
    }

    public function messages(): array
    {
        return [
            'asset_id.required' => 'Debe seleccionar el activo intervenido.',
            'maintenance_date.before_or_equal' => 'La ejecución no puede ser futura. Programe la visita en el calendario.',
            'maintenance_date.required' => 'La fecha de mantenimiento es obligatoria.',
            'detail.required' => 'El detalle técnico de tareas ejecutadas es obligatorio.',
        ];
    }
}
