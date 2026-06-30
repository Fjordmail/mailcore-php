<?php

declare(strict_types=1);

namespace Inboxcom\Mailcore\Tests\Contract;

use Inboxcom\Mailcore\Exception\ConflictException;
use Inboxcom\Mailcore\Exception\NotAcceptableException;
use Inboxcom\Mailcore\Exception\NotFoundException;
use PHPUnit\Framework\Attributes\Group;

/**
 * Live contract tests for /domains — mutating, gated behind MAILCORE_CONTRACT_WRITE.
 *
 * Uses the single shared TEST_DOMAIN (created/destroyed by the suite) for the
 * "already exists" and alias cases, and a short-lived name derived from it
 * ("crud-<TEST_DOMAIN>") for the destructive add+remove lifecycle. All names
 * carry "-test" in the SLD. The API is eventually consistent, so existence is
 * checked by polling; /domains/list returns an empty body (200) when a domain
 * exists and 404 when it does not.
 */
#[Group('contract')]
final class DomainsContractTest extends ContractTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->requireWriteTests();
        $this->bootSharedTestDomain();
    }

    /** Remove the domain and wait until it is confirmed absent, for a clean slate. */
    private function ensureAbsent(string $domain): void
    {
        $this->quietly(fn () => $this->client->domains()->remove($domain));
        $this->waitUntil(fn () => ! $this->domainExists($domain));
    }

    public function testDomainAddRemoveLifecycle(): void
    {
        $domain = 'crud-' . static::TEST_DOMAIN;
        $this->ensureAbsent($domain);

        $this->client->domains()->add($domain);            // 201
        $this->deferRemoveDomain($domain);
        $this->assertEventually(fn () => $this->domainExists($domain), "domain {$domain} to exist after add");

        $this->client->domains()->remove($domain);         // 200
        $this->assertEventually(fn () => ! $this->domainExists($domain), "domain {$domain} to be gone after remove");
    }

    public function testAddDuplicateDomainThrowsConflict(): void
    {
        // TEST_DOMAIN already exists (created by bootSharedTestDomain).
        $this->expectException(ConflictException::class);
        $this->client->domains()->add(static::TEST_DOMAIN);
    }

    public function testAddInvalidDomainThrowsNotAcceptable(): void
    {
        $this->expectException(NotAcceptableException::class);
        $this->client->domains()->add('not a valid domain');
    }

    public function testRemoveUnknownDomainThrowsNotFound(): void
    {
        $this->expectException(NotFoundException::class);
        $this->client->domains()->remove('definitely-absent-test.example');
    }

    public function testRemoveWithoutDomainParamIsBadRequest(): void
    {
        // Omitting the domain entirely is a 400 (an invalid/unknown domain is 404).
        // The typed remove() always sends one, so hit the endpoint raw.
        self::assertSame(400, $this->rawStatus('/domains/remove'));
    }

    public function testDomainAliasLifecycle(): void
    {
        $alias = 'alias-' . static::TEST_DOMAIN;
        $this->ensureAbsent($alias);

        $this->client->domains()->addAlias(static::TEST_DOMAIN, $alias);
        $this->deferRemoveDomain($alias);
        $this->assertEventually(fn () => $this->domainExists($alias), "alias domain {$alias} to exist after addalias");
    }

    public function testAddAliasInvalidNameThrowsNotAcceptable(): void
    {
        $this->expectException(NotAcceptableException::class);
        $this->client->domains()->addAlias(static::TEST_DOMAIN, 'not a valid domain');
    }

    public function testAddAliasExistingDomainThrowsConflict(): void
    {
        // An alias whose name already exists as a domain -> 409.
        $alias = 'dupalias-' . static::TEST_DOMAIN;
        $this->ensureAbsent($alias);
        $this->client->domains()->add($alias);
        $this->deferRemoveDomain($alias);
        $this->assertEventually(fn () => $this->domainExists($alias), "domain {$alias} to exist before aliasing");

        $this->expectException(ConflictException::class);
        $this->client->domains()->addAlias(static::TEST_DOMAIN, $alias);
    }

    public function testRemoveDomainWithMailboxesThrowsNotAcceptable(): void
    {
        // A domain with related mailboxes can't be removed -> 406.
        $this->createMailbox('domain-busy'); // a mailbox on TEST_DOMAIN

        $this->expectException(NotAcceptableException::class);
        $this->client->domains()->remove(static::TEST_DOMAIN);
    }

    public function testCountDomains(): void
    {
        self::assertGreaterThan(0, $this->client->domains()->count());
    }

    // --- list params -----------------------------------------------------------

    public function testListRespectsLimit(): void
    {
        $domains = $this->client->domains()->list(limit: '0,3');

        self::assertLessThanOrEqual(3, count($domains));
        self::assertContainsOnlyString($domains);
    }

    public function testListWithFilter(): void
    {
        $domains = $this->client->domains()->list(filter: '*', limit: '0,5');

        self::assertLessThanOrEqual(5, count($domains));
        self::assertContainsOnlyString($domains);
    }

    public function testListByDomainLooksUpExisting(): void
    {
        $any = $this->client->domains()->list(limit: '0,1');
        self::assertNotEmpty($any);

        // Looking up an existing domain answers 200 with an empty body -> [] (no throw).
        self::assertIsArray($this->client->domains()->list(domain: $any[0]));
    }

    public function testListUnknownDomainThrowsNotFound(): void
    {
        $this->expectException(NotFoundException::class);
        $this->client->domains()->list(domain: 'definitely-absent-xyzzy-test.example');
    }
}
