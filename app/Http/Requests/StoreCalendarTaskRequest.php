<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCalendarTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fecha' => 'required|date',
            'hora' => 'required|string',
            'titulo' => 'required|string|min:3|max:200',
            'tipo' => 'required|string',
            'technician_id' => 'required|exists:usuarios,id',
            'asset_id' => 'nullable|exists:activos,id',
            'priority' => 'nullable|in:Baja,Media,Alta,Critica',
        ];
    }
}
