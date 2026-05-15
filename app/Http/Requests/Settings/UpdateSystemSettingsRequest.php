<?php

namespace App\Http\Requests\Settings;

use App\Enums\FiscalEnvironment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSystemSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'default_fiscal_environment' => ['required', Rule::enum(FiscalEnvironment::class)],
            'xml_storage_disk' => ['required', 'string', 'max:80'],
            'retention_days' => ['required', 'integer', 'min:30', 'max:3650'],
            'sync_frequency_minutes' => ['required', 'integer', 'min:5', 'max:1440'],
            'automation_rules' => ['nullable', 'array'],
        ];
    }
}
