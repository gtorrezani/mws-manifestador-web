<?php

namespace App\DTOs\Agent;

use Illuminate\Http\Request;

readonly class ActivateAgentData
{
    /**
     * @param  array<int, array<string, mixed>>|null  $certificateInventory
     */
    public function __construct(
        public string $activationCode,
        public string $installationId,
        public string $machineName,
        public string $version,
        public ?array $certificateInventory = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            activationCode: (string) $request->input('activation_code'),
            installationId: (string) $request->input('installation_id'),
            machineName: (string) $request->input('machine_name'),
            version: (string) $request->input('version'),
            certificateInventory: is_array($request->input('certificate_inventory')) ? $request->input('certificate_inventory') : null,
        );
    }
}
