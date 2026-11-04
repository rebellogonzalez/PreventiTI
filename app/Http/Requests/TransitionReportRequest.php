<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TransitionReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => 'required|in:En curso,Diagnóstico,Finalizado',
            'action_report' => 'nullable|string|min:5|max:5000',
            'final_diagnosis' => 'nullable|string|min:5|max:5000',
        ];
    }
}
