<?php

declare(strict_types=1);

namespace Inboxcom\Mailcore\Model;

/** A custom flag and how many mailboxes carry it (/users/listflags). */
final class FlagCount
{
    public function __construct(
        public readonly string $flag,
        public readonly int $count,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            flag: (string) ($data['flag'] ?? ''),
            count: (int) ($data['count'] ?? 0),
        );
    }
}
