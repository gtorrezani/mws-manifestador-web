<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

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
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'xml_storage_disk' => ['required', 'string', 'max:80'],
            'xml_retention_days' => ['required', 'integer', 'min:30', 'max:3650'],
            'sync_frequency_minutes' => ['required', 'integer', 'min:5', 'max:1440'],
            'automation_rules' => ['nullable', 'array'],
        ];
    }
}
