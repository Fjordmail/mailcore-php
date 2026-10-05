<?php

declare(strict_types=1);

namespace Inboxcom\Mailcore\Model;

/**
 * The per-RBL result of a /mailfilter/rbllookup, naming each RBL zone Mailcore
 * checks and whether it currently lists the queried IP.
 */
final class RblResult
{
    /** @param array<string, bool> $lists RBL zone name => whether it lists the IP. */
    public function __construct(
        public readonly string $ip,
        public readonly bool $listed,
        public readonly array $lists,
    ) {
    }

    /** @return list<string> The RBL zones that currently list the IP. */
    public function listedOn(): array
    {
        return array_values(array_keys(array_filter($this->lists)));
    }

    /**
     * @param array<string, mixed> $map Zone => "CLEAN"|"LISTED", as returned by the API.
     */
    public static function fromMap(string $ip, bool $listed, array $map): self
    {
        $lists = [];
        foreach ($map as $zone => $status) {
            $lists[(string) $zone] = is_string($status) && strtoupper($status) === 'LISTED';
        }

        return new self($ip, $listed, $lists);
    }
}
