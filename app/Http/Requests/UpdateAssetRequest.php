<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateAssetRequest extends StoreAssetRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('asset')) ?? false;
    }

    public function rules(): array
    {
        return array_replace(parent::rules(), [
            'serie' => ['required', 'string', 'max:100', Rule::unique('activos', 'serial')->ignore($this->route('asset'))],
            // null conserva una ubicación histórica que no pertenezca al catálogo.
            'ubicacion_id' => 'present|nullable|integer|in:1,2,3,4,5,6,7,8',
            'expected_version' => 'required|string|size:64',
        ]);
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'expected_version.required' => 'Vuelva a abrir la edición para cargar la versión actual del activo.',
            'expected_version.size' => 'La versión del activo no es válida. Vuelva a abrir la edición.',
        ]);
    }
}
