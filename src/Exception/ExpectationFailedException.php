<?php

declare(strict_types=1);

namespace Inboxcom\Mailcore\Exception;

/**
 * HTTP 417 — a parameter was syntactically invalid.
 *
 * E.g. "E-mail address not valid", "IPv4 address not valid",
 * "Invalid mailboxplan_id syntax".
 */
final class ExpectationFailedException extends ApiException
{
}
