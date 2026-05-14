<?php

namespace App\DTOs\Agent;

use Illuminate\Http\Request;

readonly class PollCommandsData
{
    /**
     * @param  list<string>|null  $capabilities
     */
    public function __construct(
        public int $limit,
        public ?array $capabilities = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            limit: min((int) $request->integer('limit', 5), 25),
            capabilities: is_array($request->input('capabilities')) ? array_values($request->input('capabilities')) : null,
        );
    }
}
