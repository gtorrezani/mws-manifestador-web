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
            'certificate_file' => ['required', 'file', 'max:8192', 'extensions:pfx,p12'],
            'password' => ['required', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'certificate_file.extensions' => 'O arquivo deve ter extensão .pfx ou .p12.',
            'certificate_file.file' => 'Envie um arquivo de certificado válido.',
            'certificate_file.max' => 'O arquivo de certificado não pode ter mais que 8 MB.',
            'password.required' => 'Informe a senha do certificado.',
        ];
    }
}
