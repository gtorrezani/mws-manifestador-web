<?php

namespace App\Http\Requests\Agent\V1;

use App\Enums\AgentLogLevel;
use App\Http\Requests\Agent\V1\Concerns\ForbidsSensitiveAgentSecrets;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLogsRequest extends FormRequest
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
            'entries' => ['required', 'array', 'min:1', 'max:100'],
            'entries.*.level' => ['required', 'string', Rule::enum(AgentLogLevel::class)],
            'entries.*.message' => ['required', 'string', 'max:4000'],
            'entries.*.context' => ['nullable', 'array'],
            'entries.*.occurred_at' => ['nullable', 'date'],
            'pin' => ['prohibited'],
            'a3_pin' => ['prohibited'],
            'a1_password' => ['prohibited'],
            'certificate_password' => ['prohibited'],
        ];
    }
}
