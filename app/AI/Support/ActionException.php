<?php

namespace App\AI\Support;

use RuntimeException;

final class ActionException extends RuntimeException
{
    public function __construct(public readonly string $reason, string $message, public readonly int $httpStatus)
    {
        parent::__construct($message);
    }

    public static function denied(): self
    {
        return new self('denied', 'This action is not available for your permissions.', 403);
    }

    public static function disabled(): self
    {
        return new self('disabled', 'Agent actions are disabled. Read-only questions still work.', 503);
    }

    public static function invalid(): self
    {
        return new self('invalid', 'The proposed action is invalid. Please ask again with one specific target.', 422);
    }

    public static function missing(): self
    {
        return new self('missing', 'The target no longer exists. Please make a new request.', 409);
    }

    public static function stale(): self
    {
        return new self('stale', 'The target changed since this proposal was created. Please request a new proposal.', 409);
    }

    public static function expired(): self
    {
        return new self('expired', 'This proposal expired. Please request a new one.', 410);
    }

    public static function unavailable(): self
    {
        return new self('unavailable', 'The action could not be completed. No change was saved.', 503);
    }
}
