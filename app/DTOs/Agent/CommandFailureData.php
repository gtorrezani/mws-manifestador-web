<?php

namespace App\DTOs\Agent;

use Illuminate\Http\Request;

readonly class CommandFailureData
{
    /**
     * @param  array<string, mixed>|null  $errorDetails
     */
    public function __construct(
        public string $errorCode,
        public string $errorMessage,
        public ?array $errorDetails,
        public ?string $sefazStatusCode,
        public ?string $sefazMessage,
        public ?int $durationMs,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            errorCode: (string) $request->input('error_code'),
            errorMessage: (string) $request->input('error_message'),
            errorDetails: is_array($request->input('error_details')) ? $request->input('error_details') : null,
            sefazStatusCode: is_string($request->input('sefaz_status_code')) ? $request->input('sefaz_status_code') : null,
            sefazMessage: is_string($request->input('sefaz_message')) ? $request->input('sefaz_message') : null,
            durationMs: $request->integer('duration_ms') ?: null,
        );
    }
}
