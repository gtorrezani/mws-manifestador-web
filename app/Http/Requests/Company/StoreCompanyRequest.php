<?php

namespace App\Http\Requests\Company;

use App\Enums\FiscalEnvironment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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
            'fiscal_environment' => ['required', Rule::enum(FiscalEnvironment::class)],
            'is_active' => ['boolean'],
        ];
    }
}
