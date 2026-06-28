<?php

declare(strict_types=1);

namespace Inboxcom\Mailcore\Model;

/**
 * A single mailbox as returned by /users/list when queried for one user.
 *
 * The API encodes booleans as 0/1 integers; we normalise them to real bools.
 * The complete decoded payload is kept in {@see self::$raw} so callers can read
 * fields this DTO does not model yet without waiting for the SDK to catch up.
 */
final class User
{
    /**
     * @param list<string>         $flags
     * @param list<mixed>          $passwordChanges
     * @param list<string>         $forwards
     * @param list<string>         $aliases
     * @param array<string, mixed> $raw
     */
    public function __construct(
        public readonly string $email,
        public readonly bool $active,
        public readonly bool $imap,
        public readonly bool $pop3,
        public readonly int $mailboxQuota,
        public readonly ?int $mailboxQuotaOverride,
        public readonly string $mailboxplanName,
        public readonly int $mailboxplanId,
        public readonly bool $spammer,
        public readonly bool $weakpass,
        public readonly array $flags,
        public readonly array $passwordChanges,
        public readonly array $forwards,
        public readonly array $aliases,
        public readonly ?string $lastLogin,
        public readonly array $raw,
    ) {
    }

    /**
     * @param array<string, mixed> $data Decoded /users/list payload for one user.
     */
    public static function fromArray(string $email, array $data): self
    {
        return new self(
            email: $email,
            active: (bool) ($data['active'] ?? 0),
            imap: (bool) ($data['imap'] ?? 0),
            pop3: (bool) ($data['pop3'] ?? 0),
            mailboxQuota: (int) ($data['mailbox_quota'] ?? 0),
            mailboxQuotaOverride: isset($data['mailbox_quota_override']) ? (int) $data['mailbox_quota_override'] : null,
            mailboxplanName: (string) ($data['mailboxplan_name'] ?? ''),
            mailboxplanId: (int) ($data['mailboxplan_id'] ?? 0),
            spammer: (bool) ($data['spammer'] ?? 0),
            weakpass: (bool) ($data['weakpass'] ?? 0),
            flags: array_values(array_map(strval(...), (array) ($data['flags'] ?? []))),
            passwordChanges: array_values((array) ($data['password_changes'] ?? [])),
            forwards: array_values(array_map(strval(...), (array) ($data['forwards'] ?? []))),
            aliases: array_values(array_map(strval(...), (array) ($data['aliases'] ?? []))),
            lastLogin: isset($data['last_login']) ? (string) $data['last_login'] : null,
            raw: $data,
        );
    }
}
