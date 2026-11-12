<?php

namespace Dcplibrary\Notices\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request validation for dashboard statistics API endpoint.
 */
class DashboardStatsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>|\Illuminate\Contracts\Validation\ValidationRule|string>
     */
    public function rules(): array
    {
        return [
            'date_from' => 'required|date_format:Y-m-d',
            'date_to'   => 'required|date_format:Y-m-d|after_or_equal:date_from',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date_from.required'     => 'The date_from parameter is required.',
            'date_from.date_format'  => 'The date_from must be in Y-m-d format (e.g., 2026-01-13).',
            'date_to.required'       => 'The date_to parameter is required.',
            'date_to.date_format'    => 'The date_to must be in Y-m-d format (e.g., 2026-01-13).',
            'date_to.after_or_equal' => 'The date_to must be equal to or after date_from.',
        ];
    }
}
