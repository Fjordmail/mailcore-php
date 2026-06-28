<?php

declare(strict_types=1);

namespace Inboxcom\Mailcore\Model;

/** A backup snapshot of a mailbox, as returned by /users/listsnapshots. */
final class Snapshot
{
    public function __construct(
        public readonly string $serial,
        public readonly ?string $timestamp,
        public readonly ?string $size,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            serial: (string) ($data['serial'] ?? ''),
            timestamp: isset($data['timestamp']) ? (string) $data['timestamp'] : null,
            size: isset($data['size']) ? (string) $data['size'] : null,
        );
    }
}
