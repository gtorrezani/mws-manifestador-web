<?php

namespace App\Http\Requests\CurrentCompany;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCurrentCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'company_id' => [
                'required',
                'integer',
                Rule::exists('companies', 'id')->where('is_active', true),
                Rule::exists('company_user', 'company_id')
                    ->where('user_id', (int) ($this->user()?->getAuthIdentifier() ?? 0)),
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'company_id.exists' => 'A empresa selecionada não está disponível para o seu usuário.',
        ];
    }
}
