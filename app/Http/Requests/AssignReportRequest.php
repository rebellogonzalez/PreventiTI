<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'expected_version' => 'sometimes|integer|min:0',
            'technician_id' => 'required|integer|exists:tecnicos,id',
            'reassignment_reason' => 'nullable|string|min:5|max:2000',
        ];
    }
}
