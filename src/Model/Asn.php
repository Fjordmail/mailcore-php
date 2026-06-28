<?php

declare(strict_types=1);

namespace Inboxcom\Mailcore\Model;

/** An Autonomous System observed in a mailbox's login history. */
final class Asn
{
    public function __construct(
        public readonly int $asn,
        public readonly string $name,
        public readonly string $country,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            asn: (int) ($data['asn'] ?? 0),
            name: (string) ($data['name'] ?? ''),
            country: (string) ($data['country'] ?? ''),
        );
    }
}
