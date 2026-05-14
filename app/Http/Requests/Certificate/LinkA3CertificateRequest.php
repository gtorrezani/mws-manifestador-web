<?php

namespace App\Http\Requests\Certificate;

use Illuminate\Foundation\Http\FormRequest;

class LinkA3CertificateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'agent_certificate_id' => ['required', 'integer', 'exists:agent_certificates,id'],
            'name' => ['nullable', 'string', 'max:120'],
        ];
    }
}
