<?php

declare(strict_types=1);

namespace Inboxcom\Mailcore\Tests\Contract;

use PHPUnit\Framework\Attributes\Group;

/**
 * Live contract tests for /datadump. Read-only — runs with just an API key
 * (no MAILCORE_CONTRACT_WRITE needed).
 */
#[Group('contract')]
final class DatadumpContractTest extends ContractTestCase
{
    public function testFetchLatestReturnsString(): void
    {
        // Returns the dump bytes, or a literal "Not allowed!" (HTTP 200) when the
        // key/IP isn't permitted — either way a string, never an exception.
        self::assertIsString($this->client->datadump()->fetchLatest());
    }
}
