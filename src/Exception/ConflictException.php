<?php

declare(strict_types=1);

namespace Inboxcom\Mailcore\Exception;

/**
 * HTTP 409 — the request conflicts with current state.
 *
 * Covers "User already exists", "Domain name already exists",
 * "Alias already exists", "Password has already been used the last 365 days",
 * "Mailbox already has a pending or active restore job", etc.
 */
final class ConflictException extends ApiException
{
}
