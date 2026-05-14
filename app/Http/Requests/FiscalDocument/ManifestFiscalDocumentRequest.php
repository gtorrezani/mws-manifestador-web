<?php

namespace App\Http\Requests\FiscalDocument;

use App\Enums\ManifestationEventType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ManifestFiscalDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'event_type' => ['required', Rule::enum(ManifestationEventType::class)],
            'justification' => [
                Rule::requiredIf($this->input('event_type') === ManifestationEventType::OperationNotPerformed->value),
                'nullable',
                'string',
                'min:15',
                'max:255',
            ],
            'confirmed' => [
                Rule::requiredIf(in_array($this->input('event_type'), [
                    ManifestationEventType::OperationConfirmation->value,
                    ManifestationEventType::OperationUnknown->value,
                    ManifestationEventType::OperationNotPerformed->value,
                ], true)),
                'boolean',
                'accepted',
            ],
        ];
    }
}
