<?php

declare(strict_types=1);

namespace Inboxcom\Mailcore\Model;

/** A single mailbox flagged by the suspicious-activity report. */
final class SuspiciousMailboxHit
{
    /**
     * @param list<string> $countries ISO 3166-1 alpha-2 codes observed.
     * @param list<Asn>    $asns       ASNs observed in the login history.
     */
    public function __construct(
        public readonly string $email,
        public readonly int $nAsn,
        public readonly int $nCountries,
        public readonly int $nIps,
        public readonly array $countries,
        public readonly array $asns,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            email: (string) ($data['email'] ?? ''),
            nAsn: (int) ($data['n_asn'] ?? 0),
            nCountries: (int) ($data['n_countries'] ?? 0),
            nIps: (int) ($data['n_ips'] ?? 0),
            countries: array_values(array_map(strval(...), (array) ($data['countries'] ?? []))),
            asns: array_values(array_map(
                static fn (mixed $a): Asn => Asn::fromArray((array) $a),
                (array) ($data['asns'] ?? []),
            )),
        );
    }
}
