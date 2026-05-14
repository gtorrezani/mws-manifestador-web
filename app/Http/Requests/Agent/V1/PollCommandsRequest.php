<?php

namespace App\Http\Requests\Agent\V1;

use App\Enums\CommandType;
use App\Http\Requests\Agent\V1\Concerns\ForbidsSensitiveAgentSecrets;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PollCommandsRequest extends FormRequest
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
            'limit' => ['nullable', 'integer', 'min:1', 'max:25'],
            'capabilities' => ['nullable', 'array'],
            'capabilities.*' => ['string', Rule::enum(CommandType::class)],
        ];
    }
}
