<?php

namespace App\DTOs\Agent;

use Illuminate\Http\Request;

readonly class HeartbeatData
{
    /**
     * @param  array<string, mixed>|null  $metrics
     * @param  array<int, array<string, mixed>>|null  $certificateInventory
     */
    public function __construct(
        public string $status,
        public string $version,
        public ?string $machineName = null,
        public ?array $metrics = null,
        public ?array $certificateInventory = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            status: (string) $request->input('status'),
            version: (string) $request->input('version'),
            machineName: is_string($request->input('machine_name')) ? $request->input('machine_name') : null,
            metrics: is_array($request->input('metrics')) ? $request->input('metrics') : null,
            certificateInventory: is_array($request->input('certificate_inventory')) ? $request->input('certificate_inventory') : null,
        );
    }
}
