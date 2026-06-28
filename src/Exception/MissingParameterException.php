<?php

declare(strict_types=1);

namespace Inboxcom\Mailcore\Exception;

/**
 * HTTP 411 — a required parameter was not supplied.
 *
 * E.g. "Password required", "Flag required", "Date required", "Serial required",
 * "Token required". Usually indicates a bug in the calling code rather than a
 * runtime condition.
 */
final class MissingParameterException extends ApiException
{
}
