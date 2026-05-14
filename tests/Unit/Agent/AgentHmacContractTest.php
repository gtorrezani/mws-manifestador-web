<?php

namespace Tests\Unit\Agent;

use App\Services\Agent\AgentHmacAuthenticator;
use Tests\TestCase;

class AgentHmacContractTest extends TestCase
{
    public function test_php_hmac_signature_matches_shared_agent_contract_fixture(): void
    {
        $fixture = $this->fixture();

        $this->assertSame($fixture['body_sha256'], hash('sha256', $fixture['body']));
        $this->assertSame(
            $fixture['signature'],
            (new AgentHmacAuthenticator)->sign(
                secret: $fixture['secret'],
                method: $fixture['method'],
                path: $fixture['path'],
                timestamp: $fixture['timestamp'],
                nonce: $fixture['nonce'],
                body: $fixture['body'],
            ),
        );
    }

    /**
     * @return array{
     *     secret: string,
     *     method: string,
     *     path: string,
     *     timestamp: int,
     *     nonce: string,
     *     body: string,
     *     body_sha256: string,
     *     signature: string
     * }
     */
    private function fixture(): array
    {
        $content = file_get_contents(base_path('tests/Fixtures/agent-hmac-contract.json'));
        self::assertIsString($content);

        $fixture = json_decode($content, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($fixture);

        foreach (['secret', 'method', 'path', 'nonce', 'body', 'body_sha256', 'signature'] as $key) {
            self::assertArrayHasKey($key, $fixture);
            self::assertIsString($fixture[$key]);
        }

        self::assertArrayHasKey('timestamp', $fixture);
        self::assertIsInt($fixture['timestamp']);

        return [
            'secret' => $fixture['secret'],
            'method' => $fixture['method'],
            'path' => $fixture['path'],
            'timestamp' => $fixture['timestamp'],
            'nonce' => $fixture['nonce'],
            'body' => $fixture['body'],
            'body_sha256' => $fixture['body_sha256'],
            'signature' => $fixture['signature'],
        ];
    }
}
