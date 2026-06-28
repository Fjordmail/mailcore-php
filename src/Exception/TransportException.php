<?php

declare(strict_types=1);

namespace Inboxcom\Mailcore\Exception;

/**
 * Thrown when the request never produced an HTTP response: DNS failure,
 * connection refused, TLS error, or an unparseable response body.
 *
 * Note: the failing URL is deliberately never included in the message,
 * because it embeds the API key.
 */
final class TransportException extends \RuntimeException implements MailcoreException
{
}
