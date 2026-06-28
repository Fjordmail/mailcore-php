<?php

declare(strict_types=1);

namespace Inboxcom\Mailcore\Model;

/** A mailbox plan as returned by /mailboxplans/list. */
final class Mailboxplan
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly int $mailboxQuota,
        public readonly bool $imap,
        public readonly bool $pop3,
        public readonly bool $smtp,
        public readonly bool $webmail,
        public readonly int $aliases,
        public readonly int $forwards,
        public readonly ?string $dateCreated,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) ($data['id'] ?? 0),
            name: (string) ($data['name'] ?? ''),
            mailboxQuota: (int) ($data['mailbox_quota'] ?? 0),
            imap: (bool) ($data['imap'] ?? 0),
            pop3: (bool) ($data['pop3'] ?? 0),
            smtp: (bool) ($data['smtp'] ?? 0),
            webmail: (bool) ($data['webmail'] ?? 0),
            aliases: (int) ($data['aliases'] ?? 0),
            forwards: (int) ($data['forwards'] ?? 0),
            dateCreated: isset($data['date_created']) ? (string) $data['date_created'] : null,
        );
    }
}
