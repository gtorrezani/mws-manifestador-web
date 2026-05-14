<?php

namespace App\Http\Requests\Agent\V1;

use App\Http\Requests\Agent\V1\Concerns\ForbidsSensitiveAgentSecrets;
use Illuminate\Foundation\Http\FormRequest;

class CompleteCommandRequest extends FormRequest
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
            'result' => ['nullable', 'array'],
            'sefaz' => ['nullable', 'array'],
            'protocol_number' => ['nullable', 'string', 'max:80'],
            'sefaz_status_code' => ['nullable', 'string', 'max:10'],
            'sefaz_message' => ['nullable', 'string', 'max:2000'],
            'duration_ms' => ['nullable', 'integer', 'min:0'],
            'request_xml' => ['nullable', 'array'],
            'request_xml.storage_disk' => ['nullable', 'string', 'max:80'],
            'request_xml.storage_path' => ['nullable', 'string', 'max:1024'],
            'request_xml.content_hash' => ['nullable', 'string', 'max:128'],
            'response_xml' => ['nullable', 'array'],
            'response_xml.storage_disk' => ['nullable', 'string', 'max:80'],
            'response_xml.storage_path' => ['nullable', 'string', 'max:1024'],
            'response_xml.content_hash' => ['nullable', 'string', 'max:128'],
            'pin' => ['prohibited'],
            'a3_pin' => ['prohibited'],
            'a1_password' => ['prohibited'],
            'certificate_password' => ['prohibited'],
        ];
    }
}
