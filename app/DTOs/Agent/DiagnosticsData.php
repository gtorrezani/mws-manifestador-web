<?php

namespace App\DTOs\Agent;

use Illuminate\Http\Request;

readonly class DiagnosticsData
{
    /**
     * @param  list<array<string, mixed>>  $checks
     * @param  array<string, mixed>|null  $environment
     */
    public function __construct(
        public string $status,
        public array $checks,
        public ?array $environment = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            status: (string) $request->input('status'),
            checks: is_array($request->input('checks')) ? array_values($request->input('checks')) : [],
            environment: is_array($request->input('environment')) ? $request->input('environment') : null,
        );
    }
}
