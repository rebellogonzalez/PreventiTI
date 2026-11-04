<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return $user && $user->isAdmin();
    }

    public function rules(): array
    {
        return [
            'categoria_id' => 'required|integer|exists:categorias_activo,id',
            // Áreas simuladas de la instalación, definidas a partir de la
            // referencia visual y persistidas por activo en activo_ubicaciones.
            'ubicacion_id' => 'required|integer|in:1,2,3,4,5,6,7,8',
            'nombre' => 'required|string|max:200',
            'serie' => 'required|string|max:100|unique:activos,serial',
            'responsable' => 'nullable|string|max:150',
            'frecuencia_service' => 'sometimes|required|integer|min:1|max:3650',
        ];
    }

    public function messages(): array
    {
        return [
            'categoria_id.required' => 'La categoría es obligatoria.',
            'categoria_id.exists' => 'Seleccione una categoría válida.',
            'categoria_id.integer' => 'Seleccione una categoría válida.',
            'ubicacion_id.required' => 'La ubicación es obligatoria.',
            'ubicacion_id.present' => 'Indique la ubicación o conserve la actual.',
            'ubicacion_id.in' => 'Seleccione una ubicación válida.',
            'ubicacion_id.integer' => 'Seleccione una ubicación válida.',
            'serie.required' => 'El número de serie es obligatorio.',
            'serie.max' => 'La serie admite hasta 100 caracteres.',
            'serie.unique' => 'El número de serie ya está registrado en el inventario.',
            'nombre.required' => 'El nombre o modelo del equipo es obligatorio.',
            'nombre.max' => 'El nombre admite hasta 200 caracteres.',
            'responsable.max' => 'El responsable admite hasta 150 caracteres.',
            'frecuencia_service.required' => 'Indique la frecuencia de mantenimiento.',
            'frecuencia_service.integer' => 'La frecuencia debe ser un número entero de días.',
            'frecuencia_service.min' => 'La frecuencia debe ser de al menos 1 día.',
            'frecuencia_service.max' => 'La frecuencia admite hasta 3650 días.',
        ];
    }
}
