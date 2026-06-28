<?php

declare(strict_types=1);

namespace Inboxcom\Mailcore\Model;

/** The /reports/suspicious_mailbox_activity result. */
final class SuspiciousMailboxActivityReport
{
    /**
     * @param list<string>               $skipFlags Flags that exclude a mailbox from the report.
     * @param list<SuspiciousMailboxHit> $hits      Mailboxes matching the criteria.
     */
    public function __construct(
        public readonly ?string $scannedAt,
        public readonly int $days,
        public readonly int $minAsns,
        public readonly array $skipFlags,
        public readonly array $hits,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            scannedAt: isset($data['scanned_at']) ? (string) $data['scanned_at'] : null,
            days: (int) ($data['days'] ?? 0),
            minAsns: (int) ($data['min_asns'] ?? 0),
            skipFlags: array_values(array_map(strval(...), (array) ($data['skip_flags'] ?? []))),
            hits: array_values(array_map(
                static fn (mixed $h): SuspiciousMailboxHit => SuspiciousMailboxHit::fromArray((array) $h),
                (array) ($data['hits'] ?? []),
            )),
        );
    }
}
