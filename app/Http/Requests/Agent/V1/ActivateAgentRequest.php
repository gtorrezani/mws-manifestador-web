<?php

namespace App\Http\Requests\Agent\V1;

use App\Http\Requests\Agent\V1\Concerns\ForbidsSensitiveAgentSecrets;
use Illuminate\Foundation\Http\FormRequest;

class ActivateAgentRequest extends FormRequest
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
            'activation_code' => ['required', 'string', 'min:6', 'max:64'],
            'installation_id' => ['required', 'string', 'max:120'],
            'machine_name' => ['required', 'string', 'max:120'],
            'version' => ['required', 'string', 'max:40'],
            'certificate_inventory' => ['nullable', 'array'],
            'certificate_inventory.*.thumbprint' => ['nullable', 'string', 'max:120'],
            'certificate_inventory.*.subject_name' => ['nullable', 'string', 'max:255'],
            'certificate_inventory.*.valid_until' => ['nullable', 'date'],
            'pin' => ['prohibited'],
            'a3_pin' => ['prohibited'],
            'a1_password' => ['prohibited'],
            'certificate_password' => ['prohibited'],
        ];
    }
}
