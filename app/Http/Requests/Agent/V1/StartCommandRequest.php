<?php

namespace App\Http\Requests\Agent\V1;

use App\Http\Requests\Agent\V1\Concerns\ForbidsSensitiveAgentSecrets;
use Illuminate\Foundation\Http\FormRequest;

class StartCommandRequest extends FormRequest
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
            'agent_time' => ['nullable', 'date'],
        ];
    }
}
