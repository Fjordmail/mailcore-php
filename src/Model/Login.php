<?php

declare(strict_types=1);

namespace Inboxcom\Mailcore\Model;

/**
 * A login record. The shape varies slightly by endpoint, so every field is
 * nullable and only those present in the payload are populated:
 *
 *   /users/detailedlastlogin  -> ip, service, timestamp
 *   /users/latestlogins       -> email, ip, service, timestamp
 *   /users/withlastloginbefore -> email, timestamp
 */
final class Login
{
    public function __construct(
        public readonly ?string $email,
        public readonly ?string $ip,
        public readonly ?string $service,
        public readonly ?string $timestamp,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            email: isset($data['email']) ? (string) $data['email'] : null,
            ip: isset($data['ip']) ? (string) $data['ip'] : null,
            service: isset($data['service']) ? (string) $data['service'] : null,
            timestamp: isset($data['timestamp']) ? (string) $data['timestamp'] : null,
        );
    }
}
