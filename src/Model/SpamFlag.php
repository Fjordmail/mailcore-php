<?php

declare(strict_types=1);

namespace Inboxcom\Mailcore\Model;

/** A recently set spam/abuse flag (/mailfilter/latestspamflags). */
final class SpamFlag
{
    public function __construct(
        public readonly string $email,
        public readonly string $flag,
        public readonly ?string $dateSet,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            email: (string) ($data['email'] ?? ''),
            flag: (string) ($data['flag'] ?? ''),
            dateSet: isset($data['date_set']) ? (string) $data['date_set'] : null,
        );
    }
}
