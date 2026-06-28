<?php

declare(strict_types=1);

namespace Inboxcom\Mailcore\Exception;

/**
 * Marker interface implemented by every exception this package throws,
 * so callers can catch all of them with a single `catch (MailcoreException $e)`.
 */
interface MailcoreException extends \Throwable
{
}
