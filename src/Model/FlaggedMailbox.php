<?php

declare(strict_types=1);

namespace Inboxcom\Mailcore\Model;

/** A mailbox carrying a given flag, with when it was set (/users/listflagged). */
final class FlaggedMailbox
{
    public function __construct(
        public readonly string $email,
        public readonly ?string $dateSet,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            email: (string) ($data['email'] ?? ''),
            dateSet: isset($data['date_set']) ? (string) $data['date_set'] : null,
        );
    }
}
