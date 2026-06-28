<?php

declare(strict_types=1);

namespace Inboxcom\Mailcore\Tests\Resource;

use Inboxcom\Mailcore\Exception\ExpectationFailedException;
use Inboxcom\Mailcore\Exception\MissingParameterException;
use Inboxcom\Mailcore\Model\FlagCount;
use Inboxcom\Mailcore\Model\FlaggedMailbox;
use Inboxcom\Mailcore\Model\Login;
use Inboxcom\Mailcore\Model\RestoreJob;
use Inboxcom\Mailcore\Model\Service;
use Inboxcom\Mailcore\Model\Snapshot;
use Inboxcom\Mailcore\Model\User;
use Inboxcom\Mailcore\Resource\Users;
use Inboxcom\Mailcore\Tests\MailcoreTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class UsersTest extends MailcoreTestCase
{
    // --- action endpoints: assert the path and exact query they send ---------

    /** @return iterable<string, array{\Closure(Users): void, string, array<string, string>}> */
    public static function actionProvider(): iterable
    {
        // Per project convention, add/remove use a .demo.test local-part suffix.
        yield 'add' => [
            static fn (Users $u) => $u->add('holger.demo.test@example.com', 'P@ssw0rd123', 4),
            '/users/add',
            ['email' => 'holger.demo.test@example.com', 'password' => 'P@ssw0rd123', 'mailboxplan' => '4'],
        ];
        yield 'remove' => [
            static fn (Users $u) => $u->remove('holger.demo.test@example.com'),
            '/users/remove',
            ['email' => 'holger.demo.test@example.com'],
        ];
        yield 'activate' => [
            static fn (Users $u) => $u->activate('a.demo.test@example.com'),
            '/users/activate',
            ['email' => 'a.demo.test@example.com'],
        ];
        yield 'deactivate' => [
            static fn (Users $u) => $u->deactivate('a.demo.test@example.com'),
            '/users/deactivate',
            ['email' => 'a.demo.test@example.com'],
        ];
        yield 'toggleActive' => [
            static fn (Users $u) => $u->toggleActive('a.demo.test@example.com'),
            '/users/toggleactive',
            ['email' => 'a.demo.test@example.com'],
        ];
        yield 'addAlias' => [
            static fn (Users $u) => $u->addAlias('a.demo.test@example.com', 'alias.demo.test@example.com'),
            '/users/addalias',
            ['email' => 'a.demo.test@example.com', 'alias' => 'alias.demo.test@example.com'],
        ];
        yield 'removeAlias' => [
            static fn (Users $u) => $u->removeAlias('a.demo.test@example.com', 'alias.demo.test@example.com'),
            '/users/removealias',
            ['email' => 'a.demo.test@example.com', 'alias' => 'alias.demo.test@example.com'],
        ];
        yield 'addForward' => [
            static fn (Users $u) => $u->addForward('a.demo.test@example.com', 'b@example.com'),
            '/users/addforward',
            ['email' => 'a.demo.test@example.com', 'forward' => 'b@example.com'],
        ];
        yield 'removeForward' => [
            static fn (Users $u) => $u->removeForward('a.demo.test@example.com', 'b@example.com'),
            '/users/removeforward',
            ['email' => 'a.demo.test@example.com', 'forward' => 'b@example.com'],
        ];
        yield 'newPassword' => [
            static fn (Users $u) => $u->newPassword('a.demo.test@example.com', 'P@ssw0rd123'),
            '/users/newpassword',
            ['email' => 'a.demo.test@example.com', 'password' => 'P@ssw0rd123'],
        ];
        yield 'newMailboxPlan' => [
            static fn (Users $u) => $u->newMailboxPlan('a.demo.test@example.com', 26),
            '/users/newmailboxplan',
            ['email' => 'a.demo.test@example.com', 'mailboxplan' => '26'],
        ];
        yield 'setSpamTolerance' => [
            static fn (Users $u) => $u->setSpamTolerance('a.demo.test@example.com', 5),
            '/users/setspamtolerance',
            ['email' => 'a.demo.test@example.com', 'score' => '5'],
        ];
        yield 'logLogin' => [
            static fn (Users $u) => $u->logLogin('a.demo.test@example.com', Service::Imap, '8.8.8.8'),
            '/users/loglogin',
            ['email' => 'a.demo.test@example.com', 'service' => 'imap', 'ip' => '8.8.8.8'],
        ];
        yield 'setMaxMailsSentPerDay' => [
            static fn (Users $u) => $u->setMaxMailsSentPerDay('a.demo.test@example.com', 200),
            '/users/setmaxmailssentperday',
            ['email' => 'a.demo.test@example.com', 'mailsperday' => '200'],
        ];
        yield 'resetMailsSentPerDay' => [
            static fn (Users $u) => $u->resetMailsSentPerDay('a.demo.test@example.com'),
            '/users/resetmailssentperday',
            ['email' => 'a.demo.test@example.com'],
        ];
        yield 'setFlag' => [
            static fn (Users $u) => $u->setFlag('a.demo.test@example.com', 'test'),
            '/users/setflag',
            ['email' => 'a.demo.test@example.com', 'flag' => 'test'],
        ];
        yield 'unflag' => [
            static fn (Users $u) => $u->unflag('a.demo.test@example.com', 'test'),
            '/users/unflag',
            ['email' => 'a.demo.test@example.com', 'flag' => 'test'],
        ];
        yield 'restoreSnapshot' => [
            static fn (Users $u) => $u->restoreSnapshot('a.demo.test@example.com', '4b74a4f81e'),
            '/users/restoresnapshot',
            ['email' => 'a.demo.test@example.com', 'serial' => '4b74a4f81e'],
        ];
        yield 'temporaryAccess' => [
            static fn (Users $u) => $u->temporaryAccess('a.demo.test@example.com'),
            '/users/temporaryaccess',
            ['email' => 'a.demo.test@example.com'],
        ];
        yield 'testPasswordComplexity' => [
            static fn (Users $u) => $u->testPasswordComplexity('P@ssw0rd123'),
            '/users/testpasswordcomplexity',
            ['password' => 'P@ssw0rd123'],
        ];
    }

    /**
     * @param \Closure(Users): void   $call
     * @param array<string, string>   $expectedQuery
     */
    #[DataProvider('actionProvider')]
    public function testActionSendsExpectedRequest(\Closure $call, string $path, array $expectedQuery): void
    {
        $client = $this->client(self::empty());
        $call($client->users());

        self::assertSame($path, $this->http->lastPath());
        self::assertSame($expectedQuery, $this->http->lastQuery());
    }

    public function testPresenceOnlyFlagsAreSentAsOneOrOmitted(): void
    {
        $client = $this->client(self::empty(), self::empty());

        $client->users()->add('a.demo.test@example.com', 'P@ssw0rd123', 4, deactivated: true, ignoreReservation: true);
        self::assertSame('1', $this->http->lastQuery()['deactivated']);
        self::assertSame('1', $this->http->lastQuery()['ignorereservation']);

        $client->users()->newPassword('a.demo.test@example.com', 'P@ssw0rd123', noResetFlags: true);
        self::assertSame('1', $this->http->lastQuery()['noresetflags']);
    }

    public function testTemporaryAccessForwardsWindowAndPassword(): void
    {
        $client = $this->client(self::empty());
        $client->users()->temporaryAccess('a.demo.test@example.com', timeWindow: 5, tempPassword: 'Temp0rary!');

        self::assertSame(
            ['email' => 'a.demo.test@example.com', 'timewindow' => '5', 'temppassword' => 'Temp0rary!'],
            $this->http->lastQuery(),
        );
    }

    // --- data endpoints -------------------------------------------------------

    public function testListReturnsEmailArray(): void
    {
        $client = $this->client(self::json(['a.demo.test@example.com', 'b.demo.test@example.com']));

        self::assertSame(['a.demo.test@example.com', 'b.demo.test@example.com'], $client->users()->list());
    }

    public function testListWithExtendedSendsFlagAndMapsUsers(): void
    {
        $client = $this->client(self::json([
            ['email' => 'a.demo.test@example.com', 'active' => 1, 'mailboxplan_id' => 4],
            ['email' => 'b.demo.test@example.com', 'active' => 0, 'mailboxplan_id' => 26],
        ]));

        $users = $client->users()->list(filter: '*', extended: true);

        self::assertSame('/users/list', $this->http->lastPath());
        self::assertSame('1', $this->http->lastQuery()['extended']);
        self::assertSame('*', $this->http->lastQuery()['filter']);
        self::assertContainsOnlyInstancesOf(User::class, $users);
        self::assertSame('a.demo.test@example.com', $users[0]->email);
        self::assertTrue($users[0]->active);
        self::assertFalse($users[1]->active);
        self::assertSame(26, $users[1]->mailboxplanId);
    }

    public function testGetReturnsTypedUserWithNormalisedBooleans(): void
    {
        $client = $this->client(self::json([
            'active' => 1, 'imap' => 1, 'pop3' => 0, 'mailbox_quota' => 15360,
            'mailboxplan_name' => 'Demo Plan', 'mailboxplan_id' => 4, 'spammer' => 0, 'weakpass' => 1,
            'flags' => ['weakpass'], 'last_login' => 'imap;2025-03-11 14:08:22;1.2.3.4',
        ]));

        $user = $client->users()->get('holger.demo.test@example.com');

        self::assertInstanceOf(User::class, $user);
        self::assertSame('holger.demo.test@example.com', $user->email);
        self::assertSame('/users/list', $this->http->lastPath());
        self::assertSame(['user' => 'holger.demo.test@example.com'], $this->http->lastQuery());
        self::assertTrue($user->active);
        self::assertFalse($user->pop3);
        self::assertTrue($user->weakpass);
        self::assertSame(['weakpass'], $user->flags);
    }

    public function testCountReturnsInt(): void
    {
        $client = $this->client(self::json(42));

        self::assertSame(42, $client->users()->count(mailboxplanId: 4));
        self::assertSame(['mailboxplan_id' => '4'], $this->http->lastQuery());
    }

    public function testLookupAliasReturnsPrimaryAddress(): void
    {
        // Live returns the primary address under the `user` key (not `email`).
        $client = $this->client(self::json(['user' => 'holger.demo.test@example.com']));

        self::assertSame('holger.demo.test@example.com', $client->users()->lookupAlias('alias.demo.test@example.com'));
    }

    public function testListAliasesAndForwardsReadFromUserRecord(): void
    {
        // Both are convenience reads of the user record (/users/list?user=).
        $record = ['forwards' => ['fwd@example.com'], 'aliases' => ['alias@example.com']];
        $client = $this->client(self::json($record), self::json($record));

        self::assertSame(['fwd@example.com'], $client->users()->listForwards('bob.demo.test@example.com'));
        self::assertSame(['alias@example.com'], $client->users()->listAliases('bob.demo.test@example.com'));
        self::assertSame('/users/list', $this->http->lastPath());
    }

    public function testGetActiveSieveScriptReturnsString(): void
    {
        $client = $this->client(self::json("require [\"fileinto\"];\r\n"));

        self::assertSame("require [\"fileinto\"];\r\n", $client->users()->getActiveSieveScript('a.demo.test@example.com'));
    }

    public function testDetailedLastLoginMapsLogins(): void
    {
        $client = $this->client(self::json([
            ['ip' => '127.0.0.1', 'service' => 'WEBMAIL', 'timestamp' => '2023-10-27 10:25:23'],
        ]));

        $logins = $client->users()->detailedLastLogin('a.demo.test@example.com');

        self::assertContainsOnlyInstancesOf(Login::class, $logins);
        self::assertSame('127.0.0.1', $logins[0]->ip);
        self::assertSame('WEBMAIL', $logins[0]->service);
        self::assertNull($logins[0]->email);
    }

    public function testListWithLastLoginBeforeSendsDateAndMapsNullTimestamps(): void
    {
        $client = $this->client(self::json([
            ['email' => 'a.demo.test@example.com', 'timestamp' => null],
            ['email' => 'b.demo.test@example.com', 'timestamp' => '2023-03-11'],
        ]));

        $logins = $client->users()->listWithLastLoginBefore('2025-02-21 10:14:43', mailboxplanId: 4);

        self::assertSame('/users/withlastloginbefore', $this->http->lastPath());
        self::assertSame('2025-02-21 10:14:43', $this->http->lastQuery()['date']);
        self::assertNull($logins[0]->timestamp);
        self::assertSame('2023-03-11', $logins[1]->timestamp);
    }

    public function testListSnapshotsMapsModels(): void
    {
        $client = $this->client(self::json([
            ['serial' => '4b74a4f81e', 'timestamp' => '2025-02-10T03:00:00+01:00', 'size' => '50 MB'],
        ]));

        $snapshots = $client->users()->listSnapshots('a.demo.test@example.com');

        self::assertContainsOnlyInstancesOf(Snapshot::class, $snapshots);
        self::assertSame('4b74a4f81e', $snapshots[0]->serial);
    }

    public function testListSnapshotsTreatsEmptySentinelAsNoSnapshots(): void
    {
        // A mailbox with no snapshots responds with the bare token `EMPTY` (not JSON).
        $snapshots = $this->client(self::raw('EMPTY'))->users()->listSnapshots('a.demo.test@example.com');

        self::assertSame([], $snapshots);
    }

    public function testListRestoreJobsMapsModels(): void
    {
        $client = $this->client(self::json([
            ['snapshot_date' => '2025-02-21 03:00:00', 'date_queued' => '2025-03-11 14:40:35', 'date_started' => null, 'date_finished' => null, 'status' => 'PENDING', 'mails_restored' => 0, 'mails_ignored' => 0],
        ]));

        $jobs = $client->users()->listRestoreJobs('a.demo.test@example.com');

        self::assertContainsOnlyInstancesOf(RestoreJob::class, $jobs);
        self::assertSame('PENDING', $jobs[0]->status);
        self::assertNull($jobs[0]->dateStarted);
    }

    public function testListFlagsMapsModels(): void
    {
        $client = $this->client(self::json([['flag' => 'weakpass', 'count' => 1738]]));

        $flags = $client->users()->listFlags();

        self::assertContainsOnlyInstancesOf(FlagCount::class, $flags);
        self::assertSame('weakpass', $flags[0]->flag);
        self::assertSame(1738, $flags[0]->count);
    }

    public function testListFlaggedMapsModelsAndSendsFlag(): void
    {
        $client = $this->client(self::json([['email' => 'post.demo.test@example.org', 'date_set' => '2024-09-26 23:21:18']]));

        $flagged = $client->users()->listFlagged('test', mailboxplanId: 4);

        self::assertContainsOnlyInstancesOf(FlaggedMailbox::class, $flagged);
        self::assertSame(['flag' => 'test', 'mailboxplan_id' => '4'], $this->http->lastQuery());
        self::assertSame('post.demo.test@example.org', $flagged[0]->email);
    }

    // --- predicate endpoints --------------------------------------------------

    public function testIsAvailableTrueOn201(): void
    {
        self::assertTrue($this->client(self::empty(201))->users()->isAvailable('new.demo.test@example.com'));
    }

    public function testIsAvailableFalseWhenTakenOrNotAllowed(): void
    {
        self::assertFalse($this->client(self::error(409, 'E-mail address not available'))->users()->isAvailable('a.demo.test@example.com'));
        self::assertFalse($this->client(self::error(406, 'E-mail address not allowed'))->users()->isAvailable('a.demo.test@example.com'));
    }

    public function testIsAvailableRethrowsOnInvalidAddress(): void
    {
        $this->expectException(ExpectationFailedException::class);
        $this->client(self::error(417, 'E-mail address not valid'))->users()->isAvailable('nope');
    }

    public function testIsReserved(): void
    {
        // 200 == reserved, 404 == not reserved (or an existing mailbox).
        self::assertTrue($this->client(self::empty(200))->users()->isReserved('a.demo.test@example.com'));
        self::assertFalse($this->client(self::error(404, 'E-mail address is not reserved'))->users()->isReserved('a.demo.test@example.com'));
    }

    public function testVerifyPassword(): void
    {
        self::assertTrue($this->client(self::empty(200))->users()->verifyPassword('a.demo.test@example.com', 'right'));
        self::assertFalse($this->client(self::error(406, 'Password is not correct'))->users()->verifyPassword('a.demo.test@example.com', 'wrong'));
    }

    public function testVerifyPasswordRethrowsOnMissingParameter(): void
    {
        $this->expectException(MissingParameterException::class);
        $this->client(self::error(411, 'Password required'))->users()->verifyPassword('a.demo.test@example.com', '');
    }

    public function testTestPasswordComplexityThrowsOnReuse(): void
    {
        $this->expectException(\Inboxcom\Mailcore\Exception\ConflictException::class);
        $this->client(self::error(409, 'Password has already been used the last 365 days'))
            ->users()->testPasswordComplexity('P@ssw0rd123', 'a.demo.test@example.com');
    }
}
