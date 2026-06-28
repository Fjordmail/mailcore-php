<?php

declare(strict_types=1);

namespace Inboxcom\Mailcore\Tests\Contract;

use Inboxcom\Mailcore\Model\Mailboxplan;
use PHPUnit\Framework\Attributes\Group;

/**
 * Live contract tests for /mailboxplans. Read-only — runs with just an API key
 * (no MAILCORE_CONTRACT_WRITE needed).
 */
#[Group('contract')]
final class MailboxplansContractTest extends ContractTestCase
{
    public function testListReturnsTypedPlans(): void
    {
        $plans = $this->client->mailboxplans()->list();

        self::assertNotEmpty($plans);
        self::assertContainsOnlyInstancesOf(Mailboxplan::class, $plans);

        // The DTO maps real data, not defaults: a plan has an id, a name and a
        // creation date. (Booleans/counts vary per plan, so only id/name/date
        // can be asserted by value.)
        $plan = $plans[0];
        self::assertGreaterThan(0, $plan->id);
        self::assertNotSame('', $plan->name);
        self::assertNotNull($plan->dateCreated);
    }

    public function testListResponseHasExactlyTheDocumentedKeys(): void
    {
        // Check the raw response so a dropped/renamed key is caught (the SDK's
        // DTO would silently default a missing key) AND so a new, unmodelled key
        // is caught too — the set must match exactly, order-independent.
        $raw = $this->rawJson('/mailboxplans/list');
        self::assertIsArray($raw);
        self::assertNotEmpty($raw);

        self::assertEqualsCanonicalizing(
            ['id', 'name', 'mailbox_quota', 'imap', 'pop3', 'smtp', 'webmail', 'aliases', 'forwards', 'date_created'],
            array_keys($raw[0]),
            'mailbox plan response keys differ from the modelled set',
        );
    }
}

