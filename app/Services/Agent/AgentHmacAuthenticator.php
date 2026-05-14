<?php

namespace App\Services\Agent;

use App\Models\Agent;
use App\Models\AgentCredential;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class AgentHmacAuthenticator
{
    public function authenticate(Request $request): Agent
    {
        $agentUuid = $request->header('X-MWS-Agent-Id');
        $timestamp = $request->header('X-MWS-Timestamp');
        $nonce = $request->header('X-MWS-Nonce');
        $bodyHash = $request->header('X-MWS-Body-SHA256');
        $signature = $request->header('X-MWS-Signature');

        if (! $agentUuid || ! $timestamp || ! $nonce || ! $bodyHash || ! $signature) {
            throw new UnauthorizedHttpException('HMAC', 'Missing HMAC authentication headers.');
        }

        if (! ctype_digit((string) $timestamp)) {
            throw new UnauthorizedHttpException('HMAC', 'Invalid timestamp.');
        }

        $tolerance = (int) config('agent.auth.timestamp_tolerance_seconds', 300);
        if (abs(time() - (int) $timestamp) > $tolerance) {
            throw new UnauthorizedHttpException('HMAC', 'Expired timestamp.');
        }

        $expectedBodyHash = hash('sha256', $request->getContent() ?: '');
        if (! hash_equals($expectedBodyHash, strtolower((string) $bodyHash))) {
            throw new UnauthorizedHttpException('HMAC', 'Invalid body hash.');
        }

        /** @var Agent|null $agent */
        $agent = Agent::query()
            ->with('credential')
            ->where('uuid', $agentUuid)
            ->first();

        if (! $agent || ! $agent->credential) {
            throw new UnauthorizedHttpException('HMAC', 'Unknown agent.');
        }

        if ($agent->revoked_at || $agent->credential->revoked_at) {
            throw new AccessDeniedHttpException('Agent has been revoked.');
        }

        $nonceKey = sprintf('agent:%s:nonce:%s', $agent->id, $nonce);
        if (! Cache::add($nonceKey, true, $tolerance + 60)) {
            throw new UnauthorizedHttpException('HMAC', 'Nonce has already been used.');
        }

        $canonical = $this->canonicalString($request, (string) $timestamp, (string) $nonce, $expectedBodyHash);
        if ($this->matchesCredential($agent->credential, $canonical, (string) $signature)) {
            return $agent;
        }

        throw new UnauthorizedHttpException('HMAC', 'Invalid signature.');
    }

    public function sign(string $secret, string $method, string $path, int $timestamp, string $nonce, string $body): string
    {
        $canonical = strtoupper($method)."\n".$path."\n".$timestamp."\n".$nonce."\n".hash('sha256', $body);

        return hash_hmac('sha256', $canonical, $secret);
    }

    private function canonicalString(Request $request, string $timestamp, string $nonce, string $bodyHash): string
    {
        return strtoupper($request->getMethod())."\n"
            .$request->getPathInfo()."\n"
            .$timestamp."\n"
            .$nonce."\n"
            .$bodyHash;
    }

    private function matchesCredential(AgentCredential $credential, string $canonical, string $signature): bool
    {
        $activeSecret = Crypt::decryptString($credential->encrypted_secret_payload);
        $activeSignature = hash_hmac('sha256', $canonical, $activeSecret);

        if (hash_equals($activeSignature, strtolower($signature))) {
            return true;
        }

        if (! $credential->pending_encrypted_secret_payload || $credential->pending_secret_expires_at?->isPast()) {
            return false;
        }

        $pendingSecret = Crypt::decryptString($credential->pending_encrypted_secret_payload);
        $pendingSignature = hash_hmac('sha256', $canonical, $pendingSecret);

        if (! hash_equals($pendingSignature, strtolower($signature))) {
            return false;
        }

        $credential->forceFill([
            'secret_hash' => $credential->pending_secret_hash,
            'encrypted_secret_payload' => $credential->pending_encrypted_secret_payload,
            'pending_secret_hash' => null,
            'pending_encrypted_secret_payload' => null,
            'pending_secret_expires_at' => null,
            'last_rotated_at' => now(),
        ])->save();

        return true;
    }
}
