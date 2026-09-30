<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Rules\E164PhoneNumber;

/**
 * STORE INQUIRY — PHP port of the shared validation contract
 *. Error messages are byte-identical to
 * tests/contract.test.ts. Each violation fires EXACTLY one field error
 * (bail per field), privileged fields never survive into validated().
 */
class StoreInquiryRequest extends FormRequest
{
    public const PROJECT_TYPE_OPTIONS = [
        'Website Development',
        'Web Application',
        'Business System',
        'Booking / Reservation System',
        'UI/UX & Product Design',
        'Custom Digital Product',
        'Other',
    ];

    public const BUDGET_OPTIONS = [
        'Under $1,000',
        '$1,000 – $3,000',
        '$3,000 – $5,000',
        '$5,000 – $10,000',
        '$10,000+',
        'Not sure yet',
    ];

    public const TIMELINE_OPTIONS = [
        'As soon as possible',
        'Within 1 month',
        '1–3 months',
        '3–6 months',
        '6+ months',
        'Not sure yet',
    ];

    private const DANGEROUS_PATTERN =
        '/<\s*(script|iframe|object|embed|style|svg)\b|on\w+\s*=|javascript\s*:/i';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * 422 with our envelope {error:{code:VALIDATION_ERROR, fields}} — NOT
     * Laravel's default redirect/JSON shape.
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

    protected function prepareForValidation(): void
    {
        // Trim strings up front; empty strings for optional fields normalize to
        // null in validated() output.
        $out = [];
        foreach (['name', 'company', 'email', 'contactNumber', 'projectType', 'budget', 'timeline', 'message'] as $field) {
            $value = $this->input($field);
            $out[$field] = is_string($value) ? trim($value) : '';
        }
        $out['email'] = mb_strtolower($out['email']);
        $this->merge($out);
    }

    public function rules(): array
    {
        return [
            'name' => [
                'bail',
                'required',
                'min:2',
                'max:100',
                'regex:/[a-zA-Z]/',
                function ($attribute, $value, $fail) {
                    if ($value !== '' && preg_match(self::DANGEROUS_PATTERN, $value)) {
                        $fail('Name may not contain markup or code.');
                    }
                },
            ],
            'company' => [
                'bail',
                'nullable',
                'max:150',
                function ($attribute, $value, $fail) {
                    if ($value !== null && $value !== '' && preg_match(self::DANGEROUS_PATTERN, $value)) {
                        $fail('Company may not contain markup or code.');
                    }
                },
            ],
            'email' => [
                'bail',
                'required',
                'max:254',
                'regex:/^[^\s@]+@[^\s@]+\.[^\s@]+$/',
            ],
            // contactNumber is validated in its raw (trimmed) presentation form;
            // normalization happens in E164PhoneNumber exactly like the TS layer.
            'contactNumber' => [
                'bail',
                'required',
                new E164PhoneNumber(),
            ],
            'projectType' => [
                'bail',
                'required',
                Rule::in(self::PROJECT_TYPE_OPTIONS),
            ],
            'budget' => [
                'bail',
                'nullable',
                Rule::in(self::BUDGET_OPTIONS),
            ],
            'timeline' => [
                'bail',
                'nullable',
                Rule::in(self::TIMELINE_OPTIONS),
            ],
            'message' => [
                'bail',
                'required',
                'min:20',
                'max:5000',
                function ($attribute, $value, $fail) {
                    if ($value !== '' && preg_match(self::DANGEROUS_PATTERN, $value)) {
                        $fail('Message may not contain markup or code.');
                    }
                },
            ],
            // OPTIONAL meeting booking (spec §1) — absent/""/false = no meeting.
            // The 8-field inquiry contract is untouched; these fields only gate
            // whether a meeting is created alongside the inquiry. startsAt MUST
            // be an ISO 8601 UTC instant ('2026-09-30T10:00:00Z') — never an
            // ambiguous wall time without a timezone.
            'meeting' => ['bail', 'nullable', 'array'],
            'meeting.startsAt' => [
                'bail',
                'required_if:meeting.booking,true',
                function ($attribute, $value, $fail) {
                    if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?Z$/', $value) !== 1
                        && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $value) !== 1) {
                        $fail('Invalid meeting time format (use ISO 8601 UTC, e.g. 2026-09-30T10:00:00Z).');
                    }
                    if (! is_string($value) || \App\Support\MeetingTime::normalize($value) === null) {
                        $fail('Invalid meeting time format (use ISO 8601 UTC, e.g. 2026-09-30T10:00:00Z).');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Please tell us your name.',
            'name.min' => 'Name must be at least 2 characters.',
            'name.max' => 'Name must be at most 100 characters.',
            'name.regex' => 'Name must contain at least one letter.',
            'company.max' => 'Company must be at most 150 characters.',
            'email.required' => 'Please enter your business email.',
            'email.max' => 'Email must be at most 254 characters.',
            'email.regex' => 'Enter a valid email address.',
            'projectType.required' => 'Select what you need built.',
            'projectType.in' => 'Select one of the available options: '
                . implode(', ', self::PROJECT_TYPE_OPTIONS) . '.',
            'budget.in' => 'Select one of the available options: '
                . implode(', ', self::BUDGET_OPTIONS) . '.',
            'timeline.in' => 'Select one of the available options: '
                . implode(', ', self::TIMELINE_OPTIONS) . '.',
            'message.required' => 'Tell us about your project (at least 20 characters).',
            'message.min' => 'Please provide at least 20 characters.',
            'message.max' => 'Message must be at most 5,000 characters.',
            'contactNumber.required' => 'Please enter your contact number.',
            'meeting.startsAt.required_if' => 'Please choose a meeting time, or continue without booking.',
        ];
    }

    /**
     * Canonical 8 fields only — mass-assignment protection.
     * Optional fields normalize "" → null; non-record payloads never reach here
     * (the controller guards them before validation).
     */
    public function canonicalPayload(): array
    {
        $v = $this->validated();

        return [
            'name'          => $v['name'],
            'company'       => ($v['company'] ?? '') === '' ? null : $v['company'],
            'email'         => $v['email'],
            'contact_number' => E164PhoneNumber::normalize($v['contactNumber']),
            'project_type'  => $v['projectType'],
            'budget'        => ($v['budget'] ?? '') === '' ? null : $v['budget'],
            'timeline'      => ($v['timeline'] ?? '') === '' ? null : $v['timeline'],
            'message'       => $v['message'],
        ];
    }
}
