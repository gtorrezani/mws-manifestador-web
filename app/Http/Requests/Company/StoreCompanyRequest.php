<?php

namespace App\Http\Requests\Company;

use App\Support\Cnpj;
use Illuminate\Foundation\Http\FormRequest;

class StoreCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'cnpj' => Cnpj::normalize(is_scalar($this->input('cnpj')) ? (string) $this->input('cnpj') : null),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
            'legal_name' => ['required', 'string', 'max:255'],
            'trade_name' => ['nullable', 'string', 'max:255'],
            'cnpj' => ['required', 'digits:14'],
            'state_registration' => ['nullable', 'string', 'max:32'],
            'uf' => ['required', 'string', 'size:2'],
            'is_active' => ['boolean'],
        ];
    }
}
