<?php

declare(strict_types=1);

namespace Inboxcom\Mailcore\Exception;

/**
 * HTTP 404 — the referenced entity does not exist.
 *
 * Inspect {@see ApiException::$statusMsg} to tell apart "User not found",
 * "Domain name not found", "Alias not found", "Forward policy not found", etc.
 */
final class NotFoundException extends ApiException
{
}
