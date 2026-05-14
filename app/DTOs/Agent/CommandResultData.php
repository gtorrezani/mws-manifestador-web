<?php

namespace App\DTOs\Agent;

use Illuminate\Http\Request;

readonly class CommandResultData
{
    /**
     * @param  array<string, mixed>|null  $result
     * @param  array<string, mixed>|null  $sefaz
     * @param  array<string, mixed>|null  $requestXml
     * @param  array<string, mixed>|null  $responseXml
     */
    public function __construct(
        public ?array $result,
        public ?array $sefaz,
        public ?array $requestXml,
        public ?array $responseXml,
        public ?string $protocolNumber,
        public ?string $sefazStatusCode,
        public ?string $sefazMessage,
        public ?int $durationMs,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            result: is_array($request->input('result')) ? $request->input('result') : null,
            sefaz: is_array($request->input('sefaz')) ? $request->input('sefaz') : null,
            requestXml: is_array($request->input('request_xml')) ? $request->input('request_xml') : null,
            responseXml: is_array($request->input('response_xml')) ? $request->input('response_xml') : null,
            protocolNumber: is_string($request->input('protocol_number')) ? $request->input('protocol_number') : null,
            sefazStatusCode: is_string($request->input('sefaz_status_code')) ? $request->input('sefaz_status_code') : null,
            sefazMessage: is_string($request->input('sefaz_message')) ? $request->input('sefaz_message') : null,
            durationMs: $request->integer('duration_ms') ?: null,
        );
    }
}
