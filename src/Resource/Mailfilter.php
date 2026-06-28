<?php

declare(strict_types=1);

namespace Inboxcom\Mailcore\Resource;

use Inboxcom\Mailcore\Exception\ConflictException;
use Inboxcom\Mailcore\Exception\ExpectationFailedException;
use Inboxcom\Mailcore\Http\Transport;
use Inboxcom\Mailcore\Model\SmtpLimitHit;
use Inboxcom\Mailcore\Model\SpamFlag;
use OpenApi\Attributes as OA;

/** Operations on the mail filter: limits, flags, white/blacklists, RBL (the `mailfilter` tag). */
final class Mailfilter
{
    public function __construct(private readonly Transport $transport)
    {
    }

    /**
     * Accounts that recently hit the outgoing SMTP limit.
     *
     * @return list<SmtpLimitHit>
     */
    #[OA\Get(
        path: '/mailfilter/latestsmtplimithits',
        operationId: 'listLatestSmtpLimitHits',
        summary: 'List latest SMTP limit hits',
        description: 'Mailboxes that recently hit their outgoing daily mail limit',
        tags: ['mailfilter'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/MailboxplanIdOpt')],
        responses: [
            new OA\Response(response: 200, description: 'Listing entries', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/SmtpLimitHit'))),
            new OA\Response(response: 417, description: 'Invalid mailboxplan_id syntax', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ],
    )]
    public function latestSmtpLimitHits(?int $mailboxplanId = null): array
    {
        return array_values(array_map(
            static fn (mixed $row): SmtpLimitHit => SmtpLimitHit::fromArray((array) $row),
            (array) $this->transport->get('/mailfilter/latestsmtplimithits', ['mailboxplan_id' => $mailboxplanId]),
        ));
    }

    /**
     * Accounts that were recently flagged (e.g. spammer, weakpass).
     *
     * @return list<SpamFlag>
     */
    #[OA\Get(
        path: '/mailfilter/latestspamflags',
        operationId: 'listLatestSpamFlags',
        summary: 'List latest set flags',
        description: 'Mailboxes that had the spammer or compromised flag set in the last 24 hours',
        tags: ['mailfilter'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/MailboxplanIdOpt')],
        responses: [
            new OA\Response(response: 200, description: 'Listing entries', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/SpamFlag'))),
            new OA\Response(response: 417, description: 'Invalid mailboxplan_id syntax', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ],
    )]
    public function latestSpamFlags(?int $mailboxplanId = null): array
    {
        return array_values(array_map(
            static fn (mixed $row): SpamFlag => SpamFlag::fromArray((array) $row),
            (array) $this->transport->get('/mailfilter/latestspamflags', ['mailboxplan_id' => $mailboxplanId]),
        ));
    }

    /**
     * Whitelisted sender addresses for a recipient.
     *
     * Returns an empty array when there are no matching entries (the API's 417).
     *
     * @return list<string>
     */
    #[OA\Get(
        path: '/mailfilter/listwhitelist',
        operationId: 'listWhitelist',
        summary: 'List whitelist',
        description: 'Return all whitelist entries for a recipient',
        tags: ['mailfilter'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/Recipient')],
        responses: [
            new OA\Response(response: 200, description: 'Listing entries', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/Email'))),
            new OA\Response(response: 417, description: 'No matching entries found', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ],
    )]
    public function listWhitelist(string $recipient): array
    {
        return $this->listEntries('/mailfilter/listwhitelist', $recipient);
    }

    /**
     * Blacklisted sender addresses for a recipient.
     *
     * Returns an empty array when there are no matching entries (the API's 417).
     *
     * @return list<string>
     */
    #[OA\Get(
        path: '/mailfilter/listblacklist',
        operationId: 'listBlacklist',
        summary: 'List blacklist',
        description: 'Return all blacklist entries for a recipient',
        tags: ['mailfilter'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/Recipient')],
        responses: [
            new OA\Response(response: 200, description: 'Listing entries', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/Email'))),
            new OA\Response(response: 417, description: 'No matching entries found', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ],
    )]
    public function listBlacklist(string $recipient): array
    {
        return $this->listEntries('/mailfilter/listblacklist', $recipient);
    }

    /**
     * Whitelist a sender for a recipient (exempt from the mail filter).
     *
     * Both arguments accept either a full address (`user@domain.com`) or a
     * whole-domain wildcard (`*@domain.com`).
     */
    #[OA\Get(
        path: '/mailfilter/whitelistsender',
        operationId: 'whitelistSender',
        summary: 'Whitelist sender',
        description: 'Whitelist a sender (address or *@domain.com) for a recipient',
        tags: ['mailfilter'],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/Recipient'),
            new OA\Parameter(ref: '#/components/parameters/Sender'),
        ],
        responses: [
            new OA\Response(response: 201, description: 'Whitelist entry added'),
            new OA\Response(response: 406, description: 'Recipient or sender address invalid', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 409, description: 'Whitelist entry already exists', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ],
    )]
    public function whitelistSender(string $recipient, string $sender): void
    {
        self::assertAddressOrDomain($recipient, 'recipient');
        self::assertAddressOrDomain($sender, 'sender');
        $this->transport->get('/mailfilter/whitelistsender', ['recipient' => $recipient, 'sender' => $sender]);
    }

    /**
     * Blacklist a sender for a recipient (always marked as junk).
     *
     * Both arguments accept either a full address (`user@domain.com`) or a
     * whole-domain wildcard (`*@domain.com`).
     */
    #[OA\Get(
        path: '/mailfilter/blacklistsender',
        operationId: 'blacklistSender',
        summary: 'Blacklist sender',
        description: 'Blacklist a sender (address or *@domain.com) for a recipient',
        tags: ['mailfilter'],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/Recipient'),
            new OA\Parameter(ref: '#/components/parameters/Sender'),
        ],
        responses: [
            new OA\Response(response: 201, description: 'Blacklist entry added'),
            new OA\Response(response: 406, description: 'Recipient or sender address invalid', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 409, description: 'Blacklist entry already exists', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ],
    )]
    public function blacklistSender(string $recipient, string $sender): void
    {
        self::assertAddressOrDomain($recipient, 'recipient');
        self::assertAddressOrDomain($sender, 'sender');
        $this->transport->get('/mailfilter/blacklistsender', ['recipient' => $recipient, 'sender' => $sender]);
    }

    /**
     * Remove a sender from a recipient's whitelist.
     *
     * Both arguments accept either a full address (`user@domain.com`) or a
     * whole-domain wildcard (`*@domain.com`).
     */
    #[OA\Get(
        path: '/mailfilter/whitedelistsender',
        operationId: 'whitedelistSender',
        summary: 'White-delist sender',
        description: 'Remove an already-added sender from a whitelist',
        tags: ['mailfilter'],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/Recipient'),
            new OA\Parameter(ref: '#/components/parameters/Sender'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Whitelist entry removed'),
            new OA\Response(response: 404, description: 'Whitelist entry not found', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ],
    )]
    public function whitedelistSender(string $recipient, string $sender): void
    {
        self::assertAddressOrDomain($recipient, 'recipient');
        self::assertAddressOrDomain($sender, 'sender');
        $this->transport->get('/mailfilter/whitedelistsender', ['recipient' => $recipient, 'sender' => $sender]);
    }

    /**
     * Remove a sender from a recipient's blacklist.
     *
     * Both arguments accept either a full address (`user@domain.com`) or a
     * whole-domain wildcard (`*@domain.com`).
     */
    #[OA\Get(
        path: '/mailfilter/blackdelistsender',
        operationId: 'blackdelistSender',
        summary: 'Black-delist sender',
        description: 'Remove an already-added sender from a blacklist',
        tags: ['mailfilter'],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/Recipient'),
            new OA\Parameter(ref: '#/components/parameters/Sender'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Blacklist entry removed'),
            new OA\Response(response: 404, description: 'Blacklist entry not found', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ],
    )]
    public function blackdelistSender(string $recipient, string $sender): void
    {
        self::assertAddressOrDomain($recipient, 'recipient');
        self::assertAddressOrDomain($sender, 'sender');
        $this->transport->get('/mailfilter/blackdelistsender', ['recipient' => $recipient, 'sender' => $sender]);
    }

    /** Clear a recipient's entire whitelist. */
    #[OA\Get(
        path: '/mailfilter/clearwhitelist',
        operationId: 'clearWhitelist',
        summary: 'Clear whitelist',
        description: "Clear all entries from the recipient's whitelist",
        tags: ['mailfilter'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/Recipient')],
        responses: [new OA\Response(response: 200, description: 'Cleared all whitelist entries')],
    )]
    public function clearWhitelist(string $recipient): void
    {
        $this->transport->get('/mailfilter/clearwhitelist', ['recipient' => $recipient]);
    }

    /** Clear a recipient's entire blacklist. */
    #[OA\Get(
        path: '/mailfilter/clearblacklist',
        operationId: 'clearBlacklist',
        summary: 'Clear blacklist',
        description: "Clear all entries from the recipient's blacklist",
        tags: ['mailfilter'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/Recipient')],
        responses: [new OA\Response(response: 200, description: 'Cleared all blacklist entries')],
    )]
    public function clearBlacklist(string $recipient): void
    {
        $this->transport->get('/mailfilter/clearblacklist', ['recipient' => $recipient]);
    }

    /**
     * Look an IPv4 address up against the RBLs Mailcore enforces.
     *
     * @return bool True if the address is currently listed (the API's 409),
     *              false if clean (200).
     */
    #[OA\Get(
        path: '/mailfilter/rbllookup',
        operationId: 'rblLookup',
        summary: 'RBL lookup',
        description: 'Look up an IPv4 address against the RBLs Mailcore enforces',
        tags: ['mailfilter'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/IPv4')],
        responses: [
            new OA\Response(response: 200, description: 'Not found on any known RBL lists'),
            new OA\Response(response: 400, description: 'IPv4 address not valid', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 409, description: 'Found listed on RBL lists', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ],
    )]
    public function isListedOnRbl(string $ip): bool
    {
        try {
            $this->transport->get('/mailfilter/rbllookup', ['ip' => $ip]);

            return false;
        } catch (ConflictException) {
            return true;
        }
    }

    /**
     * Accept a full e-mail address (`user@domain.com`) or a domain wildcard
     * (`*@domain.com`), matching what the mail-filter endpoints take.
     *
     * @throws \InvalidArgumentException for anything else.
     */
    private static function assertAddressOrDomain(string $value, string $label): void
    {
        if (filter_var($value, FILTER_VALIDATE_EMAIL) !== false) {
            return;
        }
        if (preg_match('/^\*@[^@\s]+\.[^@\s]+$/', $value) === 1) {
            return;
        }

        throw new \InvalidArgumentException(sprintf(
            'The %s must be an e-mail address (user@domain.com) or a domain wildcard (*@domain.com), got "%s".',
            $label,
            $value,
        ));
    }

    /**
     * @return list<string>
     */
    private function listEntries(string $path, string $recipient): array
    {
        try {
            /** @var list<string> $entries */
            $entries = (array) $this->transport->get($path, ['recipient' => $recipient]);

            return $entries;
        } catch (ExpectationFailedException) {
            // 417 "No matching entries found" — treat an empty list as empty, not an error.
            return [];
        }
    }
}
