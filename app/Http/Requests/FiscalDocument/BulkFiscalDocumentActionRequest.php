<?php

namespace App\Http\Requests\FiscalDocument;

use App\Enums\FiscalDocumentBulkAction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkFiscalDocumentActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $action = $this->bulkAction();

        return [
            'action' => ['required', Rule::enum(FiscalDocumentBulkAction::class)],
            'document_ids' => ['required', 'array', 'min:1', 'max:100'],
            'document_ids.*' => ['integer', 'exists:fiscal_documents,id'],
            'justification' => [
                Rule::requiredIf($action?->requiresJustification() ?? false),
                'nullable',
                'string',
                'min:15',
                'max:255',
            ],
            'confirmed' => $this->confirmedRules($action),
        ];
    }

    private function bulkAction(): ?FiscalDocumentBulkAction
    {
        $action = $this->input('action');

        return is_string($action) ? FiscalDocumentBulkAction::tryFrom($action) : null;
    }

    /**
     * @return list<mixed>
     */
    private function confirmedRules(?FiscalDocumentBulkAction $action): array
    {
        if ($action?->requiresExplicitConfirmation() !== true) {
            return ['nullable', 'boolean'];
        }

        return ['required', 'boolean', 'accepted'];
    }
}
