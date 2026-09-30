<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * UPDATE INQUIRY — approved operational modifications only: status and/or
 * priority (spec §39). Message is never writable through this route. Both
 * fields are OPTIONAL: a PATCH carrying neither (e.g. a hostile {message})
 * is an empty patch — valid, and a no-op that returns the row untouched,
 * exactly like every other accepted update (parity contract).
 */
class UpdateInquiryRequest extends FormRequest
{
    public const STATUSES = [
        'NEW', 'REVIEWING', 'CONTACTED', 'QUALIFIED', 'PROPOSAL', 'WON', 'LOST',
    ];

    public const PRIORITIES = ['LOW', 'MEDIUM', 'HIGH', 'URGENT'];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * 422 with our envelope {error:{code:VALIDATION_ERROR, fields}} (G7).
     */
    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator): void
    {
        $fields = [];
        foreach ($validator->errors()->toArray() as $field => $messages) {
            $fields[$field] = $messages[0];
        }

        throw new \Illuminate\Http\Exceptions\HttpResponseException(
            \App\Services\ApiResponse::validation($fields)
        );
    }

    public function rules(): array
    {
        return [
            'status' => ['bail', 'nullable', 'string', Rule::in(self::STATUSES)],
            'priority' => ['bail', 'nullable', 'string', Rule::in(self::PRIORITIES)],
        ];
    }

    public function messages(): array
    {
        return [
            'status.in' => 'Invalid status.',
            'priority.in' => 'Invalid priority.',
        ];
    }
}
