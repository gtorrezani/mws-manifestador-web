<?php

namespace App\Http\Requests\Agent\V1;

use App\Enums\AgentStatus;
use App\Http\Requests\Agent\V1\Concerns\ForbidsSensitiveAgentSecrets;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HeartbeatRequest extends FormRequest
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
            'status' => ['required', 'string', Rule::enum(AgentStatus::class)],
            'version' => ['required', 'string', 'max:40'],
            'machine_name' => ['nullable', 'string', 'max:120'],
            'metrics' => ['nullable', 'array'],
            'certificate_inventory' => ['nullable', 'array'],
            'pin' => ['prohibited'],
            'a3_pin' => ['prohibited'],
            'a1_password' => ['prohibited'],
            'certificate_password' => ['prohibited'],
        ];
    }
}
