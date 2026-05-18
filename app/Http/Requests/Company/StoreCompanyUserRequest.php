<?php

namespace App\Http\Requests\Company;

use App\Rules\ValidCpf;
use App\Support\Cpf;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCompanyUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'cpf' => Cpf::normalize(is_scalar($this->input('cpf')) ? (string) $this->input('cpf') : null),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'cpf' => ['required', 'digits:11', new ValidCpf, Rule::unique('users', 'cpf')],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'is_active' => ['boolean'],
        ];
    }
}
