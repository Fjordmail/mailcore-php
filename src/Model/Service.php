<?php

declare(strict_types=1);

namespace Inboxcom\Mailcore\Model;

/**
 * The protocols/services Mailcore recognises for login logging and reporting.
 */
enum Service: string
{
    case Imap = 'imap';
    case Pop3 = 'pop3';
    case Webmail = 'webmail';
    case Smtp = 'smtp';
}
