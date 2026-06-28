<?php

declare(strict_types=1);

namespace Inboxcom\Mailcore\Tests;

use Inboxcom\Mailcore\Model\Asn;
use Inboxcom\Mailcore\Model\FlagCount;
use Inboxcom\Mailcore\Model\FlaggedMailbox;
use Inboxcom\Mailcore\Model\Login;
use Inboxcom\Mailcore\Model\Mailboxplan;
use Inboxcom\Mailcore\Model\RestoreJob;
use Inboxcom\Mailcore\Model\Service;
use Inboxcom\Mailcore\Model\Snapshot;
use Inboxcom\Mailcore\Model\SmtpLimitHit;
use Inboxcom\Mailcore\Model\SpamFlag;
use Inboxcom\Mailcore\Model\SuspiciousMailboxActivityReport;
use Inboxcom\Mailcore\Model\User;
use PHPUnit\Framework\TestCase;

final class ModelTest extends TestCase
{
    public function testUserFromArrayNormalisesAndDefaults(): void
    {
        $user = User::fromArray('a.demo.test@example.com', ['active' => 1, 'imap' => 0]);

        self::assertSame('a.demo.test@example.com', $user->email);
        self::assertTrue($user->active);
        self::assertFalse($user->imap);
        self::assertFalse($user->pop3);                  // missing -> default false
        self::assertSame([], $user->flags);              // missing -> empty list
        self::assertNull($user->lastLogin);
        self::assertNull($user->mailboxQuotaOverride);   // missing -> null
        self::assertSame([], $user->passwordChanges);    // missing -> empty list
        self::assertSame([], $user->forwards);           // missing -> empty list
        self::assertSame([], $user->aliases);            // missing -> empty list
        self::assertSame(['active' => 1, 'imap' => 0], $user->raw);
    }

    public function testUserMapsQuotaOverridePasswordChangesForwardsAndAliases(): void
    {
        $user = User::fromArray('a.demo.test@example.com', [
            'mailbox_quota_override' => 20480,
            'password_changes' => ['2025-01-02', '2025-03-04'],
            'forwards' => ['fwd1@example.com', 'fwd2@example.com'],
            'aliases' => ['alias1@example.com', 'alias2@example.com'],
        ]);

        self::assertSame(20480, $user->mailboxQuotaOverride);
        self::assertSame(['2025-01-02', '2025-03-04'], $user->passwordChanges);
        self::assertSame(['fwd1@example.com', 'fwd2@example.com'], $user->forwards);
        self::assertSame(['alias1@example.com', 'alias2@example.com'], $user->aliases);
    }

    public function testUserFlagsAreReindexedStrings(): void
    {
        $user = User::fromArray('a.demo.test@example.com', ['flags' => [3 => 'weakpass', 7 => 'test']]);

        self::assertSame(['weakpass', 'test'], $user->flags);
    }

    public function testMailboxplanNormalisesBooleans(): void
    {
        $plan = Mailboxplan::fromArray(['id' => 4, 'name' => 'Demo Plan', 'imap' => 1, 'webmail' => 0]);

        self::assertSame(4, $plan->id);
        self::assertTrue($plan->imap);
        self::assertFalse($plan->webmail);
        self::assertNull($plan->dateCreated);
    }

    public function testLoginOnlyPopulatesPresentKeys(): void
    {
        $login = Login::fromArray(['email' => 'a.demo.test@example.com', 'timestamp' => null]);

        self::assertSame('a.demo.test@example.com', $login->email);
        self::assertNull($login->ip);
        self::assertNull($login->service);
        self::assertNull($login->timestamp);
    }

    public function testSnapshotAndRestoreJob(): void
    {
        $snapshot = Snapshot::fromArray(['serial' => 'abc', 'timestamp' => 't', 'size' => '50 MB']);
        self::assertSame('abc', $snapshot->serial);

        $job = RestoreJob::fromArray(['status' => 'SUCCESS', 'mails_restored' => 10, 'mails_ignored' => 100, 'date_started' => null]);
        self::assertSame('SUCCESS', $job->status);
        self::assertSame(10, $job->mailsRestored);
        self::assertNull($job->dateStarted);
    }

    public function testSmallValueObjects(): void
    {
        self::assertSame(1738, FlagCount::fromArray(['flag' => 'weakpass', 'count' => 1738])->count);
        self::assertSame('a.demo.test@example.com', FlaggedMailbox::fromArray(['email' => 'a.demo.test@example.com', 'date_set' => 'd'])->email);
        self::assertSame('spammer', SpamFlag::fromArray(['email' => 'a.demo.test@example.com', 'flag' => 'spammer'])->flag);
        self::assertSame('1.2.3.4', SmtpLimitHit::fromArray(['email' => 'a.demo.test@example.com', 'ip' => '1.2.3.4'])->ip);

        $asn = Asn::fromArray(['asn' => 64500, 'name' => 'EXAMPLE-AS', 'country' => 'FI']);
        self::assertSame(64500, $asn->asn);
        self::assertSame('FI', $asn->country);
    }

    public function testSuspiciousReportDefaultsToEmptyCollections(): void
    {
        $report = SuspiciousMailboxActivityReport::fromArray(['days' => 14, 'min_asns' => 5]);

        self::assertSame(14, $report->days);
        self::assertSame([], $report->skipFlags);
        self::assertSame([], $report->hits);
        self::assertNull($report->scannedAt);
    }

    public function testServiceEnum(): void
    {
        self::assertSame('webmail', Service::Webmail->value);
        self::assertSame(Service::Imap, Service::from('imap'));
        self::assertNull(Service::tryFrom('ftp'));
    }
}
