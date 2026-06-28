<?php

declare(strict_types=1);

namespace Inboxcom\Mailcore\Model;

/** A mailbox that recently hit the outgoing SMTP limit (/mailfilter/latestsmtplimithits). */
final class SmtpLimitHit
{
    public function __construct(
        public readonly string $email,
        public readonly ?string $ip,
        public readonly ?string $lastHit,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            email: (string) ($data['email'] ?? ''),
            ip: isset($data['ip']) ? (string) $data['ip'] : null,
            lastHit: isset($data['last_hit']) ? (string) $data['last_hit'] : null,
        );
    }
}
