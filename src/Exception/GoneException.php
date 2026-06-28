<?php

declare(strict_types=1);

namespace Inboxcom\Mailcore\Exception;

/**
 * HTTP 410 — the resource is intentionally unavailable.
 *
 * Most commonly "E-mail address is reserved" (a removed mailbox whose address
 * is held back under the identity-theft policy; override with
 * `ignoreReservation`), and "Serial does not exist" for snapshot restores.
 */
final class GoneException extends ApiException
{
}
