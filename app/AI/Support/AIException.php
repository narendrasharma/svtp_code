<?php

namespace App\AI\Support;

use RuntimeException;

final class AIException extends RuntimeException
{
    public function __construct(public readonly string $reason, string $message, public readonly int $httpStatus)
    {
        parent::__construct($message);
    }

    public static function disabled(): self
    {
        return new self('disabled', 'AI is disabled.', 503);
    }

    public static function unconfigured(): self
    {
        return new self('unconfigured', 'AI is not configured.', 503);
    }

    public static function authentication(): self
    {
        return new self('authentication', 'AI provider authentication failed.', 503);
    }

    public static function rateLimited(): self
    {
        return new self('rate_limited', 'AI provider is rate limited. Try again later.', 429);
    }

    public static function assistantRateLimited(string $provider): self
    {
        $name = match ($provider) {
            'gemini' => 'Gemini',
            'openai' => 'OpenAI',
            'claude' => 'Claude',
            'azure' => 'Azure Foundry',
            default => 'AI',
        };

        return new self('assistant_rate_limited', $name.' Assistant rate limit reached. Try again later.', 429);
    }

    public static function timeout(): self
    {
        return new self('timeout', 'AI provider timed out. Try again later.', 504);
    }

    public static function unavailable(): self
    {
        return new self('unavailable', 'AI provider is unavailable. Try again later.', 503);
    }

    public static function invalidResponse(): self
    {
        return new self('invalid_response', 'AI provider returned an invalid response.', 502);
    }

    public static function unsupportedTools(): self
    {
        return new self('unsupported_tools', 'The selected AI provider or model does not support the assistant.', 503);
    }

    public static function invalidProviderRequest(): self
    {
        return new self('invalid_provider_request', 'The AI provider rejected the assistant request.', 502);
    }

    public static function providerAccessDenied(): self
    {
        return new self('provider_access_denied', 'The AI provider denied access to this model.', 503);
    }

    public static function deploymentUnavailable(): self
    {
        return new self('deployment_unavailable', 'The selected AI deployment is unavailable.', 503);
    }

    public static function quotaExhausted(): self
    {
        return new self('quota_exhausted', 'The AI provider quota is exhausted.', 503);
    }

    public static function unsupportedEmbeddingModel(): self
    {
        return new self('unsupported_embedding_model', 'The selected knowledge embedding model is unavailable.', 503);
    }

    public static function invalidEmbeddingInput(): self
    {
        return new self('invalid_embedding_input', 'Knowledge text is too large to embed.', 422);
    }

    public static function agentLimit(): self
    {
        return new self('agent_limit', 'The assistant reached its safe tool limit. Please ask a narrower question.', 422);
    }

    public static function fromHttpStatus(int $status): self
    {
        return match ($status) {
            401, 403 => self::authentication(),
            429 => self::rateLimited(),
            408, 504 => self::timeout(),
            default => self::unavailable(),
        };
    }
}
