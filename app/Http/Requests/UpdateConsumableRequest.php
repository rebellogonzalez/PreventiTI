<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateConsumableRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return $user && ($user->isAdmin() || $user->isTechnician());
    }

    public function rules(): array
    {
        return [
            'level' => 'required|numeric|min:0|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'level.required' => 'El nivel porcentual es obligatorio.',
            'level.numeric' => 'El nivel debe ser un valor numérico.',
            'level.min' => 'El nivel no puede ser menor a 0%.',
            'level.max' => 'El nivel no puede ser superior a 100%.',
        ];
    }
}
