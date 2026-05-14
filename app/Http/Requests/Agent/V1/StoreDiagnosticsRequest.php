<?php

namespace App\Http\Requests\Agent\V1;

use App\Enums\AgentDiagnosticStatus;
use App\Http\Requests\Agent\V1\Concerns\ForbidsSensitiveAgentSecrets;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDiagnosticsRequest extends FormRequest
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
            'status' => ['required', 'string', Rule::enum(AgentDiagnosticStatus::class)],
            'checks' => ['required', 'array'],
            'checks.*.name' => ['required', 'string', 'max:120'],
            'checks.*.status' => ['required', 'string', Rule::enum(AgentDiagnosticStatus::class)],
            'checks.*.message' => ['nullable', 'string', 'max:2000'],
            'environment' => ['nullable', 'array'],
            'pin' => ['prohibited'],
            'a3_pin' => ['prohibited'],
            'a1_password' => ['prohibited'],
            'certificate_password' => ['prohibited'],
        ];
    }
}
