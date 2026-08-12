<?php

declare(strict_types=1);

namespace Inboxcom\Mailcore\Model;

/**
 * A host currently on the BPL (bruteforce-prevention block list), as carried by
 * the 409 response of /mailfilter/bpllookup.
 */
final class BplListing
{
    /** @param list<string> $sampleUsernames Sample usernames tried during the abuse. */
    public function __construct(
        public readonly string $ip,
        public readonly ?string $dateAdded,
        public readonly ?int $timeframeMinutes,
        public readonly array $sampleUsernames,
    ) {
    }

    /** @param array<string, mixed> $data The decoded 409 body. */
    public static function fromArray(string $ip, array $data): self
    {
        /** @var list<string> $sample */
        $sample = array_values(array_map(
            static fn (mixed $v): string => (string) $v,
            is_array($data['sample'] ?? null) ? $data['sample'] : [],
        ));

        return new self(
            ip: $ip,
            dateAdded: isset($data['date_added']) ? (string) $data['date_added'] : null,
            timeframeMinutes: isset($data['timeframe_min']) ? (int) $data['timeframe_min'] : null,
            sampleUsernames: $sample,
        );
    }
}
