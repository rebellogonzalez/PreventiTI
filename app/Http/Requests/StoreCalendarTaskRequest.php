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
            'fecha' => 'required|date_format:Y-m-d',
            'hora' => 'bail|required|date_format:H:i',
            'titulo' => 'required|string|min:3|max:200',
            'submission_id' => 'nullable|uuid',
            'report_id' => 'nullable|integer|min:1|required_if:tipo,reporte|prohibited_unless:tipo,reporte',
            'tipo' => 'required|in:mantenimiento,reporte,consumible,red,auditoria',
            'technician_id' => 'required|exists:usuarios,id',
            'asset_id' => 'nullable|exists:activos,id',
            'priority' => 'nullable|in:Baja,Media,Alta,Critica',
        ];
    }

    public function messages(): array
    {
        return [
            'hora.required' => 'Seleccione una hora para la tarea.',
            'hora.date_format' => 'Indique una hora en formato de 24 horas (HH:MM), entre 00:00 y 23:59.',
        ];
    }
}
