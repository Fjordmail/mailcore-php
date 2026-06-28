<?php

declare(strict_types=1);

namespace Inboxcom\Mailcore\Exception;

/**
 * HTTP 406 — the request was understood but rejected on policy grounds.
 *
 * Covers, among others, "Password complexity not met", "Domain name not valid",
 * "E-mail address not allowed", "Password is not correct" and the various
 * "address invalid" cases. Branch on {@see ApiException::$statusMsg} to tell
 * them apart.
 */
final class NotAcceptableException extends ApiException
{
}
