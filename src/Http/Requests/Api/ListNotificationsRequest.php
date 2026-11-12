<?php

namespace Dcplibrary\Notices\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request validation for list notifications API endpoint.
 */
class ListNotificationsRequest extends FormRequest
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
            'search'                  => 'nullable|string|max:255',
            'date_from'               => 'nullable|date_format:Y-m-d',
            'date_to'                 => 'nullable|date_format:Y-m-d|after_or_equal:date_from',
            'status'                  => 'nullable|string|in:pending,delivered,failed,skipped',
            'notification_type'       => 'nullable|string|max:50',
            'delivery_option_id'      => 'nullable|integer|exists:delivery_options,id',
            'patron_barcode'          => 'nullable|string|max:50',
            'orphaned'                => 'nullable|string|in:0,1',
            'lx_starter_failure_only' => 'nullable|string|in:0,1',
            'sort'                    => 'nullable|string|in:first_exported_at,status,notification_type,delivery_option_id,patron_barcode,title',
            'dir'                     => 'nullable|string|in:asc,desc',
            'per_page'                => 'nullable|integer|min:1|max:100',
            'page'                    => 'nullable|integer|min:1',
            'include_totals'          => 'nullable|boolean',
        ];
    }
}
