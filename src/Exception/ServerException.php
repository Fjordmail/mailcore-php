<?php

declare(strict_types=1);

namespace Inboxcom\Mailcore\Exception;

/** HTTP 5xx — the Mailcore backend failed to process an otherwise valid request. */
final class ServerException extends ApiException
{
}
