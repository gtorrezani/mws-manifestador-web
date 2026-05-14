<?php

namespace App\Http\Requests\Agent\V1;

use App\Http\Requests\Agent\V1\Concerns\ForbidsSensitiveAgentSecrets;
use Illuminate\Foundation\Http\FormRequest;

class FailCommandRequest extends FormRequest
{
    use ForbidsSensitiveAgentSecrets;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'error_code' => ['required', 'string', 'max:120'],
            'error_message' => ['required', 'string', 'max:4000'],
            'error_details' => ['nullable', 'array'],
            'sefaz_status_code' => ['nullable', 'string', 'max:10'],
            'sefaz_message' => ['nullable', 'string', 'max:2000'],
            'duration_ms' => ['nullable', 'integer', 'min:0'],
            'pin' => ['prohibited'],
            'a3_pin' => ['prohibited'],
            'a1_password' => ['prohibited'],
            'certificate_password' => ['prohibited'],
        ];
    }
}
