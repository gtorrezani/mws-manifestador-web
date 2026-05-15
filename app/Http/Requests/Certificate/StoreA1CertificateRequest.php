<?php

namespace App\Http\Requests\Certificate;

use Illuminate\Foundation\Http\FormRequest;

class StoreA1CertificateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:120'],
            'certificate_file' => ['required', 'file', 'max:8192', 'mimes:pfx,p12'],
            'password' => ['required', 'string', 'max:255'],
        ];
    }
}
