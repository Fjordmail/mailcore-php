<?php

declare(strict_types=1);

namespace Inboxcom\Mailcore\Model;

/** A mailbox restore job, as returned by /users/listrestorejobs. */
final class RestoreJob
{
    /** One of PENDING, SUCCESS, ERROR. */
    public function __construct(
        public readonly ?string $snapshotDate,
        public readonly ?string $dateQueued,
        public readonly ?string $dateStarted,
        public readonly ?string $dateFinished,
        public readonly string $status,
        public readonly int $mailsRestored,
        public readonly int $mailsIgnored,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            snapshotDate: isset($data['snapshot_date']) ? (string) $data['snapshot_date'] : null,
            dateQueued: isset($data['date_queued']) ? (string) $data['date_queued'] : null,
            dateStarted: isset($data['date_started']) ? (string) $data['date_started'] : null,
            dateFinished: isset($data['date_finished']) ? (string) $data['date_finished'] : null,
            status: (string) ($data['status'] ?? ''),
            mailsRestored: (int) ($data['mails_restored'] ?? 0),
            mailsIgnored: (int) ($data['mails_ignored'] ?? 0),
        );
    }
}
