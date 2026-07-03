<?php

declare(strict_types=1);

namespace Inboxcom\Mailcore\Tests\Contract;

use Inboxcom\Mailcore\Exception\ApiException;
use Inboxcom\Mailcore\Exception\ConflictException;
use Inboxcom\Mailcore\Exception\ExpectationFailedException;
use Inboxcom\Mailcore\Exception\GoneException;
use Inboxcom\Mailcore\Exception\MissingParameterException;
use Inboxcom\Mailcore\Exception\NotAcceptableException;
use Inboxcom\Mailcore\Exception\NotFoundException;
use Inboxcom\Mailcore\Model\FlagCount;
use Inboxcom\Mailcore\Model\Login;
use Inboxcom\Mailcore\Model\Service;
use PHPUnit\Framework\Attributes\Group;

/**
 * Live contract tests for /users — mutating, gated behind MAILCORE_CONTRACT_WRITE.
 *
 * Each test drives the documented success and error responses. Every mailbox is
 * a `.demo.test` address created under the test plan and removed at teardown.
 *
 * Not deterministically testable here (and so deliberately omitted): generic
 * 400 "Bad request", 406 "E-mail address not allowed" (policy-dependent),
 * 5xx server errors, the async restore-snapshot callback, and a successful
 * snapshot restore (a fresh mailbox has no snapshots).
 */
#[Group('contract')]
final class UsersContractTest extends ContractTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->requireWriteTests();
        $this->bootSharedTestDomain();
    }

    // --- add / get / remove lifecycle + reservation -------------------------

    public function testAddGetRemoveLifecycle(): void
    {
        $email = $this->demoEmail('lifecycle');

        // add -> 201 Created
        self::assertSame(201, $this->rawStatus('/users/add', [
            'email' => $email,
            'password' => self::VALID_PASSWORD,
            'mailboxplan' => static::mailboxPlanId(),
        ]));
        $this->deferRemoveMailbox($email);

        // becomes readable -> get 200, and the SDK decodes a typed User on the test plan
        $this->assertEventually(
            fn () => $this->rawStatus('/users/list', ['user' => $email]) === 200,
            "mailbox {$email} to be readable after creation",
        );
        $user = $this->client->users()->get($email);
        self::assertSame($email, $user->email);
        self::assertSame(static::mailboxPlanId(), $user->mailboxplanId);

        // now taken -> checkavailability 409 Conflict
        self::assertSame(409, $this->rawStatus('/users/checkavailability', ['email' => $email]));

        // remove only QUEUES an async deletion job -> 200 (accepted), not "deleted"
        self::assertSame(200, $this->rawStatus('/users/remove', ['email' => $email]));

        // the background job eventually completes: the address stops resolving (get 404)
        $this->assertMailboxDeleted($email);

        // ...after which the deleted address is reserved -> checkreservation 200
        $this->assertEventually(
            fn () => $this->rawStatus('/users/checkreservation', ['email' => $email]) === 200,
            "address {$email} to become reserved after deletion",
            static::DELETION_TIMEOUT,
        );
    }

    public function testFreshAddressIsAvailableAndNotReserved(): void
    {
        $email = $this->demoEmail('fresh-available');
        // Per-run-unique address, never added -> genuinely fresh.

        self::assertTrue($this->client->users()->isAvailable($email));
        // checkreservation: 404 (not reserved / not an existing mailbox) -> false.
        self::assertFalse($this->client->users()->isReserved($email));
    }

    public function testAddDuplicateThrowsConflict(): void
    {
        $email = $this->createMailbox('dup');

        $this->expectException(ConflictException::class);
        $this->client->users()->add($email, self::VALID_PASSWORD, static::mailboxPlanId());
    }

    public function testAddWeakPasswordThrowsNotAcceptable(): void
    {
        $email = $this->demoEmail('weakpw');
        $this->deferRemoveMailbox($email);

        $this->expectException(NotAcceptableException::class);
        $this->client->users()->add($email, self::WEAK_PASSWORD, static::mailboxPlanId());
    }

    public function testAddEmptyPasswordThrowsNotAcceptable(): void
    {
        // An empty password fails the complexity policy (406) — NOT "password required".
        $this->expectException(NotAcceptableException::class);
        $this->client->users()->add($this->demoEmail('emptypw'), '', static::mailboxPlanId());
    }

    public function testAddWithoutPasswordParamIsMissingParameter(): void
    {
        // 411 "password required" only when the param is omitted entirely; the typed
        // add() always sends one (an empty string -> 406), so omit it via a raw call.
        self::assertSame(411, $this->rawStatus('/users/add', [
            'email' => $this->demoEmail('nopw'),
            'mailboxplan' => static::mailboxPlanId(),
        ]));
    }

    public function testAddUnknownMailboxPlanThrowsNotFound(): void
    {
        $email = $this->demoEmail('badplan');
        $this->deferRemoveMailbox($email);

        $this->expectException(NotFoundException::class);
        $this->client->users()->add($email, self::VALID_PASSWORD, 999_999);
    }

    public function testGetUnknownUserThrowsNotFound(): void
    {
        $this->expectException(NotFoundException::class);
        $this->client->users()->get($this->demoEmail('ghost'));
    }

    public function testGetResponseHasExactlyTheModelledKeys(): void
    {
        $email = $this->createMailbox('keys');

        $raw = $this->rawJson('/users/list', ['user' => $email]);
        self::assertIsArray($raw);

        // Hard conformance: the single-user response must contain exactly these keys.
        // Note: `email` is NOT in the body — it's the lookup input; the SDK injects
        // it into the User DTO from the query argument.
        self::assertEqualsCanonicalizing(
            [
                'active', 'imap', 'pop3', 'mailbox_quota', 'mailbox_quota_override',
                'mailboxplan_name', 'mailboxplan_id', 'date_created', 'last_login',
                'spammer', 'weakpass', 'allowed_mails_sent_per_day', 'mails_sent_current_day',
                'mailbox_messages', 'mailbox_usage', 'mailbox_quotapct', 'days_over_quota',
                'flags', 'password_changes', 'forwards', 'aliases', 'spamtolerance',
            ],
            array_keys($raw),
            'single-user response keys differ from the expected set',
        );
    }

    public function testExtendedListItemHasExactlyTheModelledKeys(): void
    {
        // The extended LIST returns a compact per-user record — a genuinely
        // different shape from the single-user get above (which returns the full
        // record and ignores `extended`). Both deserialize into the User DTO, so
        // pin this shape too; a renamed/extra key here would silently default in User.
        $raw = $this->rawJson('/users/list', ['extended' => 1, 'limit' => '0,1']);
        self::assertIsArray($raw);
        if ($raw === []) {
            self::markTestIncomplete('No users available to verify the extended-list shape.');
        }

        self::assertEqualsCanonicalizing(
            ['email', 'active', 'mailboxplan_id'],
            array_keys($raw[0]),
            'extended-list item keys differ from the expected set',
        );
    }

    public function testRemoveUnknownUserThrowsNotFound(): void
    {
        $this->expectException(NotFoundException::class);
        $this->client->users()->remove($this->demoEmail('ghost-remove'));
    }

    // --- counting -----------------------------------------------------------

    public function testCountUsers(): void
    {
        self::assertGreaterThanOrEqual(0, $this->client->users()->count(domain: static::TEST_DOMAIN));
        self::assertGreaterThanOrEqual(0, $this->client->users()->count(mailboxplanId: static::mailboxPlanId()));
    }

    // --- logins -------------------------------------------------------------

    public function testDetailedLastLoginReturnsArray(): void
    {
        $email = $this->createMailbox('detailed');

        self::assertContainsOnlyInstancesOf(Login::class, $this->client->users()->detailedLastLogin($email));
    }

    public function testDetailedLastLoginUnknownThrowsNotFound(): void
    {
        $this->expectException(NotFoundException::class);
        $this->client->users()->detailedLastLogin($this->demoEmail('ghost-dll'));
    }

    public function testListWithLastLoginBeforeAcceptsDateOnly(): void
    {
        self::assertContainsOnlyInstancesOf(Login::class, $this->client->users()->listWithLastLoginBefore('2000-01-01'));
    }

    public function testListWithLastLoginBeforeRejectsDateTime(): void
    {
        $this->expectException(ExpectationFailedException::class);
        $this->client->users()->listWithLastLoginBefore('2020-01-01 00:00:00');
    }

    public function testLatestLoginsAreTyped(): void
    {
        self::assertContainsOnlyInstancesOf(Login::class, $this->client->users()->latestLogins());
    }

    public function testListFlagsAreTyped(): void
    {
        self::assertContainsOnlyInstancesOf(FlagCount::class, $this->client->users()->listFlags());
    }

    public function testLogLogin(): void
    {
        $email = $this->createMailbox('loglogin');

        $this->client->users()->logLogin($email, Service::Imap, '8.8.8.8');
        self::assertTrue(true); // 200, no exception
    }

    public function testLogLoginInvalidIpThrows(): void
    {
        $email = $this->createMailbox('loglogin-badip');

        $this->expectException(ExpectationFailedException::class);
        $this->client->users()->logLogin($email, Service::Imap, 'not-an-ip');
    }

    // --- aliases ------------------------------------------------------------

    public function testAliasLifecycle(): void
    {
        $email = $this->createMailbox('alias-primary');
        $alias = $this->demoEmail('alias-secondary');

        // addAlias can transiently 404 ("source ... does not relate to a domain")
        // while a fresh domain settles, so retry until the server accepts it.
        $this->assertWriteAcceptedEventually(
            fn () => $this->client->users()->addAlias($email, $alias),
            "addAlias({$alias} -> {$email}) to be accepted once the domain has settled",
        );
        $this->defer(fn () => $this->quietly(fn () => $this->client->users()->removeAlias($email, $alias)));

        // while it exists, the alias resolves to its primary address
        $this->assertEventually(fn () => $this->client->users()->lookupAlias($alias) === $email, "alias {$alias} to resolve to {$email}");

        // remove it...
        $this->client->users()->removeAlias($email, $alias);

        // ...and verify the removal took effect: the alias no longer resolves (lookupAlias 404s).
        // A thrown NotFoundException is the success signal here, so probe it explicitly rather
        // than letting assertEventually treat the throw as "not ready yet".
        $this->assertEventually(function () use ($alias): bool {
            try {
                $this->client->users()->lookupAlias($alias);

                return false; // still resolving
            } catch (NotFoundException) {
                return true; // gone
            }
        }, "alias {$alias} to stop resolving after removal");
    }

    public function testLookupUnknownAliasThrowsNotFound(): void
    {
        $this->expectException(NotFoundException::class);
        $this->client->users()->lookupAlias($this->demoEmail('ghost-alias'));
    }

    // --- forwards -----------------------------------------------------------

    public function testForwardLifecycle(): void
    {
        $email = $this->createMailbox('forward-src');
        $forward = 'sdk-contract-forward@example.com';

        $this->client->users()->addForward($email, $forward);
        // The forward isn't immediately removable (read-after-write lag); poll
        // removeForward until it stops 404-ing.
        $this->assertEventually(function () use ($email, $forward): bool {
            $this->client->users()->removeForward($email, $forward);

            return true;
        }, "forward {$forward} to become removable");
    }

    public function testRemoveUnknownForwardThrowsNotFound(): void
    {
        $email = $this->createMailbox('forward-none');

        $this->expectException(NotFoundException::class);
        $this->client->users()->removeForward($email, 'never-added@example.com');
    }

    // --- passwords ----------------------------------------------------------

    public function testTestPasswordComplexityAcceptsValid(): void
    {
        $this->client->users()->testPasswordComplexity(self::VALID_PASSWORD);
        self::assertTrue(true);
    }

    public function testTestPasswordComplexityRejectsWeak(): void
    {
        $this->expectException(NotAcceptableException::class);
        $this->client->users()->testPasswordComplexity(self::WEAK_PASSWORD);
    }

    public function testNewPasswordThenVerify(): void
    {
        $email = $this->createMailbox('newpw');
        $newPassword = 'Rotated!Pass42x';

        $this->client->users()->newPassword($email, $newPassword);

        $this->assertEventually(fn () => $this->client->users()->verifyPassword($email, $newPassword), 'the new password to verify');
        self::assertFalse($this->client->users()->verifyPassword($email, 'Wrong!Pass99z'));
    }

    public function testNewPasswordRejectsWeak(): void
    {
        $email = $this->createMailbox('newpw-weak');

        $this->expectException(NotAcceptableException::class);
        $this->client->users()->newPassword($email, self::WEAK_PASSWORD);
    }

    public function testVerifyPasswordRequiresPassword(): void
    {
        $email = $this->createMailbox('verifypw');

        $this->expectException(MissingParameterException::class);
        $this->client->users()->verifyPassword($email, '');
    }

    // --- sieve / plan / state ----------------------------------------------

    public function testGetActiveSieveScriptReturnsString(): void
    {
        // A freshly created mailbox has no script yet (see the 404 test below) and
        // there is no API to set one, so verify the 200 happy path on an existing
        // user — most carry at least the placeholder "/* empty script */".
        $email = $this->sampleExistingEmail();

        try {
            self::assertIsString($this->client->users()->getActiveSieveScript($email));
        } catch (NotFoundException) {
            self::markTestIncomplete("Sampled user {$email} has no active sieve script to read.");
        }
    }

    public function testGetActiveSieveScriptForFreshMailboxThrowsNotFound(): void
    {
        // A new mailbox has no active sieve script; the endpoint 404s
        // ("Mailbox has no active sieve script") until one exists.
        $email = $this->createMailbox('sieve-none');

        $this->expectException(NotFoundException::class);
        $this->client->users()->getActiveSieveScript($email);
    }

    public function testNewMailboxPlanSamePlanSucceeds(): void
    {
        $email = $this->createMailbox('replan');

        $this->client->users()->newMailboxPlan($email, static::mailboxPlanId());
        self::assertSame(static::mailboxPlanId(), $this->client->users()->get($email)->mailboxplanId);
    }

    public function testNewMailboxPlanUnknownUserThrowsNotFound(): void
    {
        $this->expectException(NotFoundException::class);
        $this->client->users()->newMailboxPlan($this->demoEmail('ghost-plan'), static::mailboxPlanId());
    }

    public function testActivateDeactivateToggle(): void
    {
        $email = $this->createMailbox('state');

        $this->client->users()->deactivate($email);
        $this->assertEventually(fn () => ! $this->client->users()->get($email)->active, "{$email} to become inactive");

        $this->client->users()->activate($email);
        $this->assertEventually(fn () => $this->client->users()->get($email)->active, "{$email} to become active");

        $this->client->users()->toggleActive($email);
        $this->assertEventually(fn () => ! $this->client->users()->get($email)->active, "{$email} to toggle back to inactive");
    }

    public function testActivateUnknownUserThrowsNotFound(): void
    {
        $this->expectException(NotFoundException::class);
        $this->client->users()->activate($this->demoEmail('ghost-activate'));
    }

    public function testSetSpamTolerance(): void
    {
        $email = $this->createMailbox('spamtol');

        $this->client->users()->setSpamTolerance($email, 5);
        self::assertTrue(true); // 200
    }

    public function testTemporaryAccess(): void
    {
        $email = $this->createMailbox('tempaccess');

        $this->client->users()->temporaryAccess($email, timeWindow: 5);
        self::assertTrue(true); // 200
    }

    public function testMailLimitControls(): void
    {
        $email = $this->createMailbox('maillimit');

        $this->client->users()->setMaxMailsSentPerDay($email, 100);
        $this->client->users()->resetMailsSentPerDay($email);
        self::assertTrue(true); // both 200
    }

    // --- flags --------------------------------------------------------------

    public function testFlagLifecycle(): void
    {
        $email = $this->createMailbox('flagged');

        $this->client->users()->setFlag($email, 'test');
        $this->assertEventually(
            fn () => in_array($email, array_map(static fn ($f) => $f->email, $this->client->users()->listFlagged('test')), true),
            "{$email} to appear in the 'test' flag listing",
        );

        $this->client->users()->unflag($email, 'test');
    }

    public function testSetReservedFlagIsRejected(): void
    {
        $email = $this->createMailbox('flag-reserved');

        try {
            $this->client->users()->setFlag($email, 'delete');
            self::fail('Expected the reserved "delete" flag to be rejected');
        } catch (ApiException $e) {
            self::assertSame(403, $e->statusCode);
        }
    }

    // --- snapshots / restores (fresh mailbox: no data) ----------------------

    public function testListSnapshotsReturnsArray(): void
    {
        $email = $this->createMailbox('snapshots');

        // A fresh mailbox may have no snapshots yet — an array (possibly empty) is fine.
        self::assertIsArray($this->client->users()->listSnapshots($email));
    }

    public function testListRestoreJobsForFreshMailbox(): void
    {
        $email = $this->createMailbox('restorejobs');

        // No history yet: either an empty list or the API's 410 "no history".
        try {
            self::assertIsArray($this->client->users()->listRestoreJobs($email));
        } catch (GoneException $e) {
            self::assertSame(410, $e->statusCode);
        }
    }

    public function testRestoreUnknownSnapshotIsRejected(): void
    {
        $email = $this->createMailbox('restore-bad');

        $this->expectException(ApiException::class);
        $this->client->users()->restoreSnapshot($email, 'nonexistent-serial');
    }

    // --- response structure (exact keys) ---------------------------------------
    //
    // These verify the raw key set of the list responses (a dropped/renamed/new
    // key is caught — the DTOs would silently default a missing one). The login
    // feeds have global data; snapshots/restore-jobs only exist on established
    // mailboxes, so those sample an existing user (read-only) and mark the test
    // incomplete if the sample happens to have none.

    public function testLatestLoginsResponseStructure(): void
    {
        $raw = $this->rawJson('/users/latestlogins');
        self::assertIsArray($raw);
        if ($raw === []) {
            self::markTestIncomplete('No logins in the last 10 minutes to verify the shape.');
        }

        self::assertEqualsCanonicalizing(['email', 'ip', 'service', 'timestamp'], array_keys($raw[0]), 'latestlogins keys');
    }

    public function testDetailedLastLoginResponseStructure(): void
    {
        $email = $this->sampleExistingEmail();
        $raw = $this->rawJson('/users/detailedlastlogin', ['email' => $email]);
        self::assertIsArray($raw);
        if ($raw === []) {
            self::markTestIncomplete("Sampled user {$email} has no detailed last-login history.");
        }

        self::assertEqualsCanonicalizing(['ip', 'service', 'timestamp'], array_keys($raw[0]), "detailedlastlogin keys for {$email}");
    }

    public function testListWithLastLoginBeforeResponseStructure(): void
    {
        $raw = $this->rawJson('/users/withlastloginbefore', ['date' => '2020-01-01']);
        self::assertIsArray($raw);
        if ($raw === []) {
            self::markTestIncomplete('No users with a last login before the date.');
        }

        self::assertEqualsCanonicalizing(['email', 'timestamp'], array_keys($raw[0]), 'withlastloginbefore keys');
    }

    public function testListSnapshotsResponseStructure(): void
    {
        $email = $this->sampleExistingEmail();
        $raw = $this->rawJson('/users/listsnapshots', ['email' => $email]);
        if (! is_array($raw) || ! isset($raw[0]) || ! is_array($raw[0])) {
            self::markTestIncomplete("Sampled user {$email} has no snapshots.");
        }

        self::assertEqualsCanonicalizing(['serial', 'timestamp', 'size'], array_keys($raw[0]), "listsnapshots keys for {$email}");
    }

    public function testListRestoreJobsResponseStructure(): void
    {
        $email = $this->sampleExistingEmail();
        $raw = $this->rawJson('/users/listrestorejobs', ['email' => $email]);
        if (! is_array($raw) || ! isset($raw[0]) || ! is_array($raw[0])) {
            self::markTestIncomplete("Sampled user {$email} has no restore jobs.");
        }

        self::assertEqualsCanonicalizing(
            ['snapshot_date', 'date_queued', 'date_started', 'date_finished', 'status', 'mails_restored', 'mails_ignored'],
            array_keys($raw[0]),
            "listrestorejobs keys for {$email}",
        );
    }

    // --- additional response-code coverage ------------------------------------
    //
    // All codes below were confirmed live (the spec disagrees in places). The
    // typed SDK can't send malformed input, so these probe the raw status.

    public function testCheckAvailabilityResponseCodes(): void
    {
        // available -> 200 (the spec's 201 is a doc bug; the SDK accepts any 2xx)
        self::assertSame(200, $this->rawStatus('/users/checkavailability', ['email' => $this->demoEmail('avail-free')]));
        // taken -> 409
        self::assertSame(409, $this->rawStatus('/users/checkavailability', ['email' => $this->sampleExistingEmail()]));
        // disallowed local part -> 406
        self::assertSame(406, $this->rawStatus('/users/checkavailability', ['email' => 'postmaster@' . static::TEST_DOMAIN]));
        // invalid syntax -> 417
        self::assertSame(417, $this->rawStatus('/users/checkavailability', ['email' => 'not-an-email']));
    }

    public function testListWithLastLoginBeforeRequiresDate(): void
    {
        self::assertSame(411, $this->rawStatus('/users/withlastloginbefore'));
    }

    public function testListFlaggedRequiresFlag(): void
    {
        self::assertSame(411, $this->rawStatus('/users/listflagged'));
    }

    public function testListFlaggedInvalidMailboxplanIdIs417(): void
    {
        self::assertSame(417, $this->rawStatus('/users/listflagged', ['flag' => 'weakpass', 'mailboxplan_id' => 'not-a-number']));
    }

    public function testListFlagsInvalidMailboxplanIdIs417(): void
    {
        self::assertSame(417, $this->rawStatus('/users/listflags', ['mailboxplan_id' => 'not-a-number']));
    }

    public function testListSnapshotsUnknownUserThrowsNotFound(): void
    {
        // Unknown user -> 404 (a known user with no snapshots returns the EMPTY sentinel instead).
        $this->expectException(NotFoundException::class);
        $this->client->users()->listSnapshots($this->demoEmail('ghost-snapshots'));
    }

    public function testLogLoginValidationCodes(): void
    {
        $email = $this->createMailbox('loglogin-val');

        // unrecognised service -> 406; missing service/ip -> 411
        self::assertSame(406, $this->rawStatus('/users/loglogin', ['email' => $email, 'service' => 'bogus', 'ip' => '1.2.3.4']));
        self::assertSame(411, $this->rawStatus('/users/loglogin', ['email' => $email]));
    }

    public function testFlagValidationCodes(): void
    {
        $email = $this->createMailbox('flag-val');

        // set/unset with no flag name -> 411
        self::assertSame(411, $this->rawStatus('/users/setflag', ['email' => $email]));
        self::assertSame(411, $this->rawStatus('/users/unflag', ['email' => $email]));
    }

    public function testRestoreSnapshotValidationCodes(): void
    {
        $email = $this->createMailbox('restore-val');

        // missing serial -> 411; unknown serial -> 410
        self::assertSame(411, $this->rawStatus('/users/restoresnapshot', ['email' => $email]));
        self::assertSame(410, $this->rawStatus('/users/restoresnapshot', ['email' => $email, 'serial' => 'nonexistent-serial']));
    }

    public function testPasswordValidationCodes(): void
    {
        $email = $this->createMailbox('pw-val');

        // testpasswordcomplexity against the current password -> 405 (matches current)
        self::assertSame(405, $this->rawStatus('/users/testpasswordcomplexity', ['email' => $email, 'password' => self::VALID_PASSWORD]));
        // newpassword with no password -> 411
        self::assertSame(411, $this->rawStatus('/users/newpassword', ['email' => $email]));
    }

    public function testPasswordReuseIsRejectedWith409(): void
    {
        // Created with VALID_PASSWORD; change away from it...
        $email = $this->createMailbox('pw-reuse');
        $this->client->users()->newPassword($email, 'Zx9!ContractSdk7q');

        // ...so the original is now a recently-used password. testpasswordcomplexity
        // reports 409 "used in the last 365 days". (newpassword does NOT yet enforce
        // this — tracked by the failing testNewPasswordShouldRejectReusedPassword.)
        self::assertSame(409, $this->rawStatus('/users/testpasswordcomplexity', [
            'email' => $email,
            'password' => self::VALID_PASSWORD,
        ]));
    }

    /**
     * newpassword SHOULD reject a password identical to the current one (405), the
     * way testpasswordcomplexity does. The live API does not yet enforce this on
     * newpassword (it returns 200), so this test FAILS on purpose — it tracks the
     * gap and turns green once the API enforces it. See the README
     * "Policy the live API does not enforce".
     */
    public function testNewPasswordShouldRejectMatchingCurrent(): void
    {
        // Freshly created: VALID_PASSWORD is the current password.
        $email = $this->createMailbox('newpw-cur');

        self::assertSame(405, $this->rawStatus('/users/newpassword', [
            'email' => $email,
            'password' => self::VALID_PASSWORD,
        ]));
    }

    /**
     * newpassword SHOULD reject a password used within the last 365 days (409), the
     * way testpasswordcomplexity does. The live API does not yet enforce this on
     * newpassword (it returns 200), so this test FAILS on purpose — it tracks the
     * gap and turns green once the API enforces it.
     */
    public function testNewPasswordShouldRejectReusedPassword(): void
    {
        // Created with VALID_PASSWORD; rotate away so VALID_PASSWORD becomes a
        // former (recently-used) password that is no longer the current one.
        $email = $this->createMailbox('newpw-reuse');
        $this->client->users()->newPassword($email, 'Zx9!RotatedPass7q');
        $this->assertEventually(
            fn () => $this->client->users()->verifyPassword($email, 'Zx9!RotatedPass7q'),
            "newpassword to activate the rotated password on {$email}",
        );

        self::assertSame(409, $this->rawStatus('/users/newpassword', [
            'email' => $email,
            'password' => self::VALID_PASSWORD,
        ]));
    }

    public function testReservedAddressIsRejectedWith410(): void
    {
        // Reserve an address: create then remove a mailbox. The reservation only
        // appears once the async deletion job finishes, so poll on the deletion budget.
        $reserved = $this->createMailbox('reserved');
        $this->client->users()->remove($reserved);
        $this->assertEventually(
            fn () => $this->rawStatus('/users/checkreservation', ['email' => $reserved]) === 200,
            "address {$reserved} to become reserved after removal",
            static::DELETION_TIMEOUT,
        );

        // Re-adding the reserved address (without ignorereservation) -> 410 "E-mail address is reserved"...
        self::assertSame(410, $this->rawStatus('/users/add', [
            'email' => $reserved,
            'password' => self::VALID_PASSWORD,
            'mailboxplan' => static::mailboxPlanId(),
        ]));
        // ...as is adding it as an alias of a live mailbox.
        $primary = $this->createMailbox('reserved-primary');
        self::assertSame(410, $this->rawStatus('/users/addalias', ['email' => $primary, 'alias' => $reserved]));
    }

    // --- more validation codes + the unknown-user 404 sweep -------------------

    public function testCheckReservationInvalidEmailIs417(): void
    {
        self::assertSame(417, $this->rawStatus('/users/checkreservation', ['email' => 'not-an-email']));
    }

    public function testAddInvalidEmailIs417(): void
    {
        self::assertSame(417, $this->rawStatus('/users/add', [
            'email' => 'not-an-email',
            'password' => self::VALID_PASSWORD,
            'mailboxplan' => static::mailboxPlanId(),
        ]));
    }

    public function testLatestLoginsInvalidMailboxplanIdIs417(): void
    {
        self::assertSame(417, $this->rawStatus('/users/latestlogins', ['mailboxplan_id' => 'not-a-number']));
    }

    /**
     * Every per-user mutation rejects an unknown mailbox with 404. (testpasswordcomplexity
     * is excluded — it validates password complexity first, so a weak password 406s before
     * the user is looked up.)
     */
    public function testUnknownUserMutationsReturnNotFound(): void
    {
        $ghost = $this->demoEmail('ghost-mutations');

        $cases = [
            ['/users/deactivate', []],
            ['/users/toggleactive', []],
            ['/users/resetmailssentperday', []],
            ['/users/setspamtolerance', ['score' => 3]],
            ['/users/setmaxmailssentperday', ['count' => 100]],
            ['/users/temporaryaccess', []],
            ['/users/verifypassword', ['password' => self::VALID_PASSWORD]],
            ['/users/newpassword', ['password' => self::VALID_PASSWORD]],
            ['/users/setflag', ['flag' => 'test']],
            ['/users/unflag', ['flag' => 'test']],
        ];

        foreach ($cases as [$path, $extra]) {
            self::assertSame(404, $this->rawStatus($path, ['email' => $ghost] + $extra), "{$path} on an unknown user");
        }
    }

    public function testAddAliasDuplicateThrowsConflict(): void
    {
        $email = $this->createMailbox('alias-dup');
        $alias = $this->demoEmail('alias-dup-secondary');
        $this->assertWriteAcceptedEventually(
            fn () => $this->client->users()->addAlias($email, $alias),
            "addAlias({$alias}) to be accepted once the domain has settled",
        );
        $this->defer(fn () => $this->quietly(fn () => $this->client->users()->removeAlias($email, $alias)));

        // Duplicate detection only kicks in once the first alias has propagated.
        $this->assertEventually(
            fn () => $this->client->users()->lookupAlias($alias) === $email,
            "alias {$alias} to propagate before re-adding",
        );

        $this->expectException(ConflictException::class);
        $this->client->users()->addAlias($email, $alias);
    }

    public function testAddForwardDuplicateThrowsConflict(): void
    {
        $email = $this->createMailbox('forward-dup');
        $forward = 'sdk-contract-dup-forward@example.com';
        $this->client->users()->addForward($email, $forward);
        $this->defer(fn () => $this->quietly(fn () => $this->client->users()->removeForward($email, $forward)));

        // The forward appears in the user record (under `forwards`, present only when
        // non-empty) once it propagates; wait for that before re-adding.
        $this->assertEventually(
            fn () => in_array($forward, $this->client->users()->get($email)->raw['forwards'] ?? [], true),
            "forward {$forward} to propagate before re-adding",
        );

        $this->expectException(ConflictException::class);
        $this->client->users()->addForward($email, $forward);
    }

    // --- parameter coverage ---------------------------------------------------

    public function testAddDeactivatedCreatesInactiveMailbox(): void
    {
        $email = $this->demoEmail('deactivated');
        $this->client->users()->add($email, self::VALID_PASSWORD, static::mailboxPlanId(), deactivated: true);
        $this->deferRemoveMailbox($email);
        $this->assertEventually(fn () => $this->mailboxExists($email), "mailbox {$email} to be readable after creation");

        self::assertFalse($this->client->users()->get($email)->active, 'a deactivated mailbox should be inactive');
    }

    public function testNewPasswordNoResetFlagsRetainsSpammerWeakpass(): void
    {
        $email = $this->createMailbox('noreset');
        $this->client->users()->setFlag($email, 'spammer');
        $this->client->users()->setFlag($email, 'weakpass');
        $this->assertEventually(
            fn () => in_array('spammer', $this->client->users()->get($email)->flags, true)
                && in_array('weakpass', $this->client->users()->get($email)->flags, true),
            "spammer+weakpass flags to be set on {$email}",
        );

        // noResetFlags keeps the flags across the password change...
        $this->client->users()->newPassword($email, 'Zx9!ContractSdk7q', noResetFlags: true);
        self::assertEqualsCanonicalizing(['spammer', 'weakpass'], $this->client->users()->get($email)->flags, 'flags retained with noResetFlags');

        // ...while a default newPassword clears them.
        $this->client->users()->newPassword($email, 'Qw3!FreshPass8mn');
        $this->assertEventually(fn () => $this->client->users()->get($email)->flags === [], "flags to be cleared after a default newPassword on {$email}");
    }

    public function testListFilterMatchesByEmail(): void
    {
        $email = $this->createMailbox('list-filter');

        $this->assertEventually(
            fn () => in_array($email, $this->client->users()->list(filter: $email, mailboxplanId: static::mailboxPlanId()), true),
            "the filter to match the created mailbox {$email}",
        );
    }

    public function testTemporaryAccessWithExplicitPassword(): void
    {
        $email = $this->createMailbox('tempaccess-pw');

        // The explicit temp password is sent as `temppassword` and accepted (200).
        $this->client->users()->temporaryAccess($email, tempPassword: 'Tmp!Pass123xy');
        self::assertSame(200, $this->rawStatus('/users/temporaryaccess', ['email' => $email, 'temppassword' => 'Tmp!Pass456zw']));
    }

    public function testAddWithIgnoreReservationBypassesReservation(): void
    {
        // Reserve an address (create -> remove -> wait for the async reservation).
        $reserved = $this->createMailbox('ignres');
        $this->client->users()->remove($reserved);
        $this->assertEventually(
            fn () => $this->rawStatus('/users/checkreservation', ['email' => $reserved]) === 200,
            "address {$reserved} to become reserved after removal",
            static::DELETION_TIMEOUT,
        );

        // Without the flag this is 410 (see testReservedAddressIsRejectedWith410); ignoreReservation overrides it.
        $this->client->users()->add($reserved, self::VALID_PASSWORD, static::mailboxPlanId(), ignoreReservation: true);
        $this->deferRemoveMailbox($reserved);
        $this->assertEventually(fn () => $this->mailboxExists($reserved), "reserved address {$reserved} to be re-created with ignoreReservation");
    }

    // --- edge cases -----------------------------------------------------------

    public function testListPaginationBoundaries(): void
    {
        // A zero count and an offset past the end both yield an empty list (not an error).
        self::assertSame([], $this->client->users()->list(limit: '0,0'));
        self::assertSame([], $this->client->users()->list(limit: '999999999,5'));
    }

    public function testCountAcceptsDomainAndPlanTogether(): void
    {
        // Both filters together are accepted (intersected), despite the CLI describing them as exclusive.
        self::assertGreaterThanOrEqual(
            0,
            $this->client->users()->count(domain: static::TEST_DOMAIN, mailboxplanId: static::mailboxPlanId()),
        );
    }

    public function testSetSpamToleranceRangeIsNotEnforced(): void
    {
        $email = $this->createMailbox('spamtol-range');

        // The documented 1..5 range is NOT enforced server-side — out-of-range is accepted (200).
        self::assertSame(200, $this->rawStatus('/users/setspamtolerance', ['email' => $email, 'score' => 9]));
        self::assertSame(200, $this->rawStatus('/users/setspamtolerance', ['email' => $email, 'score' => 0]));
    }

    public function testLogLoginAcceptsAllServices(): void
    {
        $email = $this->createMailbox('loglogin-svcs');

        foreach (Service::cases() as $service) {
            self::assertSame(
                200,
                $this->rawStatus('/users/loglogin', ['email' => $email, 'service' => $service->value, 'ip' => '1.2.3.4']),
                "loglogin should accept service {$service->value}",
            );
        }
    }
}
