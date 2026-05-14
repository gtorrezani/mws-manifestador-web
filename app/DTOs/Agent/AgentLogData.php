<?php

namespace App\DTOs\Agent;

use Illuminate\Http\Request;

readonly class AgentLogData
{
    /**
     * @param  list<array<string, mixed>>  $entries
     */
    public function __construct(
        public array $entries,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $entries = $request->input('entries', []);

        return new self(entries: is_array($entries) ? array_values($entries) : []);
    }
}
