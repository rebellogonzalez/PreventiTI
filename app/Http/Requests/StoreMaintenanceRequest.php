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
            'maintenance_date' => 'required|date',
            'detail' => 'required|string|min:5|max:2000',
        ];
    }

    public function messages(): array
    {
        return [
            'asset_id.required' => 'Debe seleccionar el activo intervenido.',
            'maintenance_date.required' => 'La fecha de mantenimiento es obligatoria.',
            'detail.required' => 'El detalle técnico de tareas ejecutadas es obligatorio.',
        ];
    }
}
