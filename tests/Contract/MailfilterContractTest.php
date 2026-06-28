<?php

declare(strict_types=1);

namespace Inboxcom\Mailcore\Tests\Contract;

use Inboxcom\Mailcore\Exception\BadRequestException;
use Inboxcom\Mailcore\Exception\ConflictException;
use Inboxcom\Mailcore\Exception\NotFoundException;
use Inboxcom\Mailcore\Model\SmtpLimitHit;
use Inboxcom\Mailcore\Model\SpamFlag;
use PHPUnit\Framework\Attributes\Group;

/**
 * Live contract tests for /mailfilter white/blacklisting — mutating, gated
 * behind MAILCORE_CONTRACT_WRITE. Entries are created against a `.demo.test`
 * recipient mailbox on the shared throwaway test domain and removed at teardown.
 */
#[Group('contract')]
final class MailfilterContractTest extends ContractTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->requireWriteTests();
        $this->bootSharedTestDomain();
    }

    private function whitelisted(string $recipient): callable
    {
        return fn (string $sender): bool => in_array($sender, $this->client->mailfilter()->listWhitelist($recipient), true);
    }

    private function blacklisted(string $recipient): callable
    {
        return fn (string $sender): bool => in_array($sender, $this->client->mailfilter()->listBlacklist($recipient), true);
    }

    public function testWhitelistLifecycle(): void
    {
        $recipient = $this->createMailbox('mf-white');
        $sender = 'friend@example.com';
        $isListed = $this->whitelisted($recipient);

        $this->client->mailfilter()->whitelistSender($recipient, $sender);
        $this->assertEventually(fn () => $isListed($sender), "{$sender} to appear on {$recipient}'s whitelist");

        $this->client->mailfilter()->whitedelistSender($recipient, $sender);
        $this->assertEventually(fn () => ! $isListed($sender), "{$sender} to leave {$recipient}'s whitelist");
    }

    public function testBlacklistLifecycle(): void
    {
        $recipient = $this->createMailbox('mf-black');
        $sender = 'spammer@example.com';
        $isListed = $this->blacklisted($recipient);

        $this->client->mailfilter()->blacklistSender($recipient, $sender);
        $this->assertEventually(fn () => $isListed($sender), "{$sender} to appear on {$recipient}'s blacklist");

        $this->client->mailfilter()->blackdelistSender($recipient, $sender);
        $this->assertEventually(fn () => ! $isListed($sender), "{$sender} to leave {$recipient}'s blacklist");
    }

    public function testWhitelistWildcardSenderAccepted(): void
    {
        $recipient = $this->createMailbox('mf-wild');
        $wildcard = '*@partner-test.example';
        $isListed = $this->whitelisted($recipient);

        $this->client->mailfilter()->whitelistSender($recipient, $wildcard);
        $this->assertEventually(fn () => $isListed($wildcard), "wildcard {$wildcard} to appear on the whitelist");

        $this->client->mailfilter()->clearWhitelist($recipient);
        $this->assertEventually(fn () => $this->client->mailfilter()->listWhitelist($recipient) === [], "{$recipient}'s whitelist to be cleared");
    }

    public function testBlacklistWildcardSenderAccepted(): void
    {
        $recipient = $this->createMailbox('mf-black-wild');
        $wildcard = '*@partner-test.example';
        $isListed = $this->blacklisted($recipient);

        $this->client->mailfilter()->blacklistSender($recipient, $wildcard);
        $this->assertEventually(fn () => $isListed($wildcard), "wildcard {$wildcard} to appear on the blacklist");

        $this->client->mailfilter()->clearBlacklist($recipient);
        $this->assertEventually(fn () => $this->client->mailfilter()->listBlacklist($recipient) === [], "{$recipient}'s blacklist to be cleared");
    }

    public function testWhitelistDuplicateThrowsConflict(): void
    {
        $recipient = $this->createMailbox('mf-dup');
        $sender = 'dupe@example.com';

        $this->client->mailfilter()->whitelistSender($recipient, $sender);
        $this->assertEventually(fn () => ($this->whitelisted($recipient))($sender), "{$sender} to be whitelisted before re-adding");

        $this->expectException(ConflictException::class);
        $this->client->mailfilter()->whitelistSender($recipient, $sender);
    }

    public function testWhitedelistUnknownThrowsNotFound(): void
    {
        $recipient = $this->createMailbox('mf-missing');

        $this->expectException(NotFoundException::class);
        $this->client->mailfilter()->whitedelistSender($recipient, 'never-added@example.com');
    }

    public function testEmptyListsReturnEmptyArray(): void
    {
        $recipient = $this->createMailbox('mf-empty');

        // Raw: an empty list responds 417 "No matching entries found"...
        self::assertSame(417, $this->rawStatus('/mailfilter/listwhitelist', ['recipient' => $recipient]));
        self::assertSame(417, $this->rawStatus('/mailfilter/listblacklist', ['recipient' => $recipient]));
        // ...which the SDK normalises to an empty array.
        self::assertSame([], $this->client->mailfilter()->listWhitelist($recipient));
        self::assertSame([], $this->client->mailfilter()->listBlacklist($recipient));
    }

    // --- symmetry: blacklist mirrors of the whitelist conflict/unknown cases ---

    public function testBlacklistDuplicateThrowsConflict(): void
    {
        $recipient = $this->createMailbox('mf-black-dup');
        $sender = 'dupe@example.com';

        $this->client->mailfilter()->blacklistSender($recipient, $sender);
        $this->assertEventually(fn () => ($this->blacklisted($recipient))($sender), "{$sender} to be blacklisted before re-adding");

        $this->expectException(ConflictException::class);
        $this->client->mailfilter()->blacklistSender($recipient, $sender);
    }

    public function testBlackdelistUnknownThrowsNotFound(): void
    {
        $recipient = $this->createMailbox('mf-black-missing');

        $this->expectException(NotFoundException::class);
        $this->client->mailfilter()->blackdelistSender($recipient, 'never-added@example.com');
    }

    // --- recipient-existence checks --------------------------------------------
    //
    // All four sender endpoints return 404 when the recipient mailbox does not
    // exist. The recipient here is a valid-format address that simply doesn't
    // exist, so it clears the SDK's client-side format guard and reaches the API.

    public function testWhitelistSenderRejectsUnknownRecipient(): void
    {
        $ghostRecipient = $this->demoEmail('mf-ghost-white'); // never created

        $this->expectException(NotFoundException::class);
        $this->client->mailfilter()->whitelistSender($ghostRecipient, 'sender@example.com');
    }

    public function testBlacklistSenderRejectsUnknownRecipient(): void
    {
        $ghostRecipient = $this->demoEmail('mf-ghost-black'); // never created

        $this->expectException(NotFoundException::class);
        $this->client->mailfilter()->blacklistSender($ghostRecipient, 'sender@example.com');
    }

    public function testWhitedelistSenderRejectsUnknownRecipient(): void
    {
        $ghostRecipient = $this->demoEmail('mf-ghost-whitedelist'); // never created

        $this->expectException(NotFoundException::class);
        $this->client->mailfilter()->whitedelistSender($ghostRecipient, 'sender@example.com');
    }

    public function testBlackdelistSenderRejectsUnknownRecipient(): void
    {
        $ghostRecipient = $this->demoEmail('mf-ghost-blackdelist'); // never created

        $this->expectException(NotFoundException::class);
        $this->client->mailfilter()->blackdelistSender($ghostRecipient, 'sender@example.com');
    }

    // --- invalid-address handling (406) ----------------------------------------
    //
    // whitelistSender()/blacklistSender() reject a malformed recipient/sender
    // client-side (InvalidArgumentException) before sending — see the unit suite.
    // Here we hit the endpoints RAW (bypassing that guard) to confirm the server
    // still answers 406 for an invalid address, on both recipient and sender.

    public function testWhitelistSenderInvalidSenderIs406(): void
    {
        $recipient = $this->createMailbox('mf-406-white-s');

        self::assertSame(406, $this->rawStatus('/mailfilter/whitelistsender', [
            'recipient' => $recipient,
            'sender' => 'not a valid address',
        ]));
    }

    public function testWhitelistSenderInvalidRecipientIs406(): void
    {
        self::assertSame(406, $this->rawStatus('/mailfilter/whitelistsender', [
            'recipient' => 'not a valid address',
            'sender' => 'friend@example.com',
        ]));
    }

    public function testBlacklistSenderInvalidSenderIs406(): void
    {
        $recipient = $this->createMailbox('mf-406-black-s');

        self::assertSame(406, $this->rawStatus('/mailfilter/blacklistsender', [
            'recipient' => $recipient,
            'sender' => 'not a valid address',
        ]));
    }

    public function testBlacklistSenderInvalidRecipientIs406(): void
    {
        self::assertSame(406, $this->rawStatus('/mailfilter/blacklistsender', [
            'recipient' => 'not a valid address',
            'sender' => 'friend@example.com',
        ]));
    }

    // --- read-only feeds + RBL -------------------------------------------------

    public function testLatestSmtpLimitHitsAreTyped(): void
    {
        self::assertContainsOnlyInstancesOf(SmtpLimitHit::class, $this->client->mailfilter()->latestSmtpLimitHits());
    }

    public function testLatestSpamFlagsAreTyped(): void
    {
        self::assertContainsOnlyInstancesOf(SpamFlag::class, $this->client->mailfilter()->latestSpamFlags());
    }

    public function testRblLookupCleanAddressReturnsFalse(): void
    {
        self::assertFalse($this->client->mailfilter()->isListedOnRbl('8.8.8.8'));
    }

    public function testRblLookupInvalidIpThrowsBadRequest(): void
    {
        $this->expectException(BadRequestException::class);
        $this->client->mailfilter()->isListedOnRbl('not-an-ip');
    }

    public function testRblLookupListedAddressReturnsTrue(): void
    {
        // 127.0.0.2 is the canonical DNSBL test entry: reliably listed -> 409 -> true.
        self::assertTrue($this->client->mailfilter()->isListedOnRbl('127.0.0.2'));
    }

    public function testLatestSmtpLimitHitsInvalidMailboxplanIdIs417(): void
    {
        // The typed latestSmtpLimitHits(?int) can't send a malformed id, so hit it raw.
        self::assertSame(417, $this->rawStatus('/mailfilter/latestsmtplimithits', ['mailboxplan_id' => 'not-a-number']));
    }

    public function testLatestSpamFlagsInvalidMailboxplanIdIs417(): void
    {
        self::assertSame(417, $this->rawStatus('/mailfilter/latestspamflags', ['mailboxplan_id' => 'not-a-number']));
    }
}
