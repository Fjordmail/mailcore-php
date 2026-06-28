<?php

declare(strict_types=1);

namespace Inboxcom\Mailcore\Tests\Contract;

use Inboxcom\Mailcore\Exception\NotFoundException;
use Inboxcom\Mailcore\MailcoreClient;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Base for the live contract suite: tests that exercise the REAL Mailcore API
 * to confirm every endpoint, parameter and response behaves as the SDK expects.
 *
 * Excluded from the default run (group "contract"); see phpunit.xml.
 *
 * Self-contained design: the suite CREATES one throwaway test domain
 * (TEST_DOMAIN, "-test" SLD) ONCE per run, runs every mailbox test under it, and
 * attempts to remove it at the end of the run — no pre-existing hosted domain
 * needed. The /domains CRUD tests derive their own short-lived names from
 * TEST_DOMAIN (e.g. "crud-<TEST_DOMAIN>").
 *
 * Gates:
 *   - A resolvable API key (MAILCORE_API_KEY or ~/.config/mailcore/config.ini),
 *     else the whole suite skips. Read-only checks need only this.
 *   - Mutating tests additionally require MAILCORE_CONTRACT_WRITE=1.
 *
 * The Mailcore API is eventually consistent: ANY write can take a few seconds
 * to be readable (and a brand-new domain takes a few seconds before it accepts
 * mailboxes). There is no fixed "settle" constant — instead every read-after-
 * write polls via {@see self::waitUntil()}, bounded by CONSISTENCY_TIMEOUT.
 *
 * Conventions: mailboxes are `<x>-<runId>.demo.test@TEST_DOMAIN` (the run id
 * keeps names unique across runs, since a removed mailbox lingers as "already in
 * use" until a background job purges it); domains carry "-test" in the SLD.
 * Per-test cleanup flags mailboxes for deletion; the domain is removed best-effort
 * at run end, with `composer contract:cleanup` as the authoritative backstop.
 */
#[Group('contract')]
abstract class ContractTestCase extends TestCase
{
    /**
     * Mailbox plan for created test mailboxes (must work on a freshly-created domain).
     * Environment-specific, so it is read from MAILCORE_CONTRACT_PLAN_ID rather than
     * hardcoded; 0 (unset) makes the mutating tests skip. Static so
     * bin/contract-cleanup.php can reuse it.
     */
    public static function mailboxPlanId(): int
    {
        return (int) (getenv('MAILCORE_CONTRACT_PLAN_ID') ?: 0);
    }

    /** The single throwaway domain the suite creates (mailbox host + base for CRUD names); "-test" SLD. (Public so bin/contract-cleanup.php can reuse it.) */
    public const TEST_DOMAIN = 'mailcore-sdk-contract-test.com';

    /**
     * Max seconds to poll for any write to become readable. Must cover fresh-domain
     * settle: on a just-provisioned TEST_DOMAIN, mailbox/alias propagation can take
     * well over 30s (on a warm domain the same ops resolve in a few seconds, so a
     * generous cap costs little — assertEventually returns as soon as the state flips).
     */
    protected const CONSISTENCY_TIMEOUT = 0.5*60;

    /**
     * Max seconds to wait for an asynchronous mailbox-deletion job to finish.
     * /users/remove only *queues* the deletion (returns 200 immediately) and a
     * background worker performs it, so this is a much larger budget than the
     * read-after-write CONSISTENCY_TIMEOUT.
     */
    protected const DELETION_TIMEOUT = 5*60;

    protected const VALID_PASSWORD = 'Sdk!Contract9xZ';
    protected const WEAK_PASSWORD = 'weak';

    protected MailcoreClient $client;
    protected string $apiKey;
    protected string $baseUri;

    /** @var list<callable(): void> LIFO cleanup callbacks. */
    private array $cleanup = [];

    private static bool $domainBooted = false;
    private static bool $runEndRemovalRegistered = false;
    private static string $runId = '';

    /** The random non-test plan chosen once per run for {@see self::sampleExistingEmail()}. */
    private static ?int $samplePlanId = null;

    protected function setUp(): void
    {
        $apiKey = self::resolveApiKey();
        if ($apiKey === null) {
            self::markTestSkipped('No Mailcore API key (set MAILCORE_API_KEY or ~/.config/mailcore/config.ini).');
        }

        $this->apiKey = $apiKey;
        $this->baseUri = self::resolveBaseUri();
        $this->client = new MailcoreClient($this->apiKey, $this->baseUri);
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->cleanup) as $fn) {
            try {
                $fn();
            } catch (\Throwable) {
                // best-effort
            }
        }
        $this->cleanup = [];
    }

    /**
     * Register a best-effort removal of the shared test domain at the END of the
     * run (once, not per class — re-creating the domain between classes races the
     * async deletion of the previous one). Mailboxes are removed per-test, but
     * deletion is async/slow, so the domain may still be 406 at run end; the
     * authoritative cleanup is `composer contract:cleanup`, run once the
     * background job has purged the flagged mailboxes.
     */
    private static function registerRunEndDomainRemoval(MailcoreClient $client): void
    {
        if (self::$runEndRemovalRegistered) {
            return;
        }
        self::$runEndRemovalRegistered = true;

        $domain = static::TEST_DOMAIN;
        register_shutdown_function(static function () use ($client, $domain): void {
            try {
                $client->domains()->remove($domain);
            } catch (\Throwable) {
                // best-effort — see contract:cleanup
            }
        });
    }

    /** Gate for any test that writes to the live server. */
    protected function requireWriteTests(): void
    {
        if (getenv('MAILCORE_CONTRACT_WRITE') !== '1') {
            self::markTestSkipped('Mutating contract test — set MAILCORE_CONTRACT_WRITE=1 to enable.');
        }
        if (static::mailboxPlanId() <= 0) {
            self::markTestSkipped('Set MAILCORE_CONTRACT_PLAN_ID to a usable mailbox plan id for the mutating tests.');
        }
    }

    /**
     * Ensure the shared throwaway test domain exists and actually accepts
     * mailboxes. Call from setUp() of mailbox tests.
     *
     * A freshly-created domain isn't usable for a few seconds, so instead of a
     * fixed sleep we poll by creating a canary mailbox until it succeeds.
     */
    protected function bootSharedTestDomain(): void
    {
        if (self::$domainBooted) {
            return;
        }

        $this->quietly(fn () => $this->client->domains()->add(static::TEST_DOMAIN));

        $canary = $this->demoEmail('boot-canary');
        $ready = false;
        $lastError = 'no attempt made';
        for ($waited = 0; $waited <= static::CONSISTENCY_TIMEOUT; $waited += 2) {
            try {
                $this->client->users()->add($canary, self::VALID_PASSWORD, static::mailboxPlanId());
                $ready = true;
                break;
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
                sleep(2);
            }
        }
        $this->quietly(fn () => $this->client->users()->remove($canary));

        if (! $ready) {
            self::fail(sprintf(
                "TEST_DOMAIN %s did not accept a mailbox (plan %d) within %ds.\nLast error: %s\n"
                . '(A "[409] already in use" usually means a prior run\'s mailbox is still pending background deletion, '
                . 'not a plan problem.)',
                static::TEST_DOMAIN,
                static::mailboxPlanId(),
                static::CONSISTENCY_TIMEOUT,
                $lastError,
            ));
        }

        self::registerRunEndDomainRemoval($this->client);
        self::$domainBooted = true;
    }

    /**
     * A `<localPart>-<runId>.demo.test@TEST_DOMAIN` address.
     *
     * The per-run id keeps mailbox names unique across runs: a removed mailbox is
     * only *flagged* for deletion and lingers (as "already in use") until a
     * background job purges it, so reusing a fixed name across runs would collide.
     */
    protected function demoEmail(string $localPart): string
    {
        return sprintf('%s-%s.demo.test@%s', $localPart, self::runId(), static::TEST_DOMAIN);
    }

    /** A short id generated once per test-process run. */
    protected static function runId(): string
    {
        if (self::$runId === '') {
            self::$runId = bin2hex(random_bytes(3));
        }

        return self::$runId;
    }

    /**
     * Create a fresh test mailbox, wait until it is readable, and register it
     * for removal at teardown.
     */
    protected function createMailbox(string $localPart, ?string $password = null): string
    {
        // demoEmail() is per-run unique (runId), so the address is freshly minted
        // and cannot pre-exist; add() is the first call. A surprise 409 here is a
        // real signal (collision/leaked state), not something to silently wipe.
        $email = $this->demoEmail($localPart);
        $this->client->users()->add(
            $email,
            $password ?? self::VALID_PASSWORD,
            static::mailboxPlanId(),
        );
        $this->deferRemoveMailbox($email);
        $this->assertEventually(fn () => $this->mailboxExists($email), "mailbox {$email} to be readable after creation");

        return $email;
    }

    protected function mailboxExists(string $email): bool
    {
        try {
            $this->client->users()->get($email);

            return true;
        } catch (NotFoundException) {
            return false;
        }
    }

    /**
     * Choose, ONCE per run, a random real mailbox plan to draw sampled users from —
     * never the empty test plan, and skipping plans with no users. Cached in
     * self::$samplePlanId and reused so every {@see self::sampleExistingEmail()}
     * call hits the same plan. Lazily invoked (only sampling tests pay for it).
     */
    private function bootSamplePlan(): int
    {
        if (self::$samplePlanId !== null) {
            return self::$samplePlanId;
        }

        $planIds = array_values(array_filter(
            array_map(static fn ($plan): int => $plan->id, $this->client->mailboxplans()->list()),
            fn (int $id): bool => $id !== static::mailboxPlanId(),
        ));
        self::assertNotEmpty($planIds, 'expected at least one non-test mailbox plan');
        shuffle($planIds);

        foreach ($planIds as $planId) {
            if ($this->client->users()->count(mailboxplanId: $planId) > 0) {
                return self::$samplePlanId = $planId;
            }
        }

        self::fail('No non-test mailbox plan has any users.');
    }

    /**
     * A RANDOM existing mailbox, so read-only tests that sample a real user don't
     * all depend on one account. Draws from the once-per-run plan ({@see
     * self::bootSamplePlan()}); within it the API has no sorting, so we offset by a
     * random index into the plan's count, falling back to the first page if a high
     * offset races a tail deletion. Re-picks the plan if it emptied out mid-run.
     */
    protected function sampleExistingEmail(): string
    {
        $email = $this->sampleEmailFromPlan($this->bootSamplePlan());
        if ($email === null) {
            self::$samplePlanId = null;
            $email = $this->sampleEmailFromPlan($this->bootSamplePlan());
        }

        self::assertNotNull($email, 'expected to sample an existing user');

        return $email;
    }

    /** Return a random user on the given plan, or null if the plan has no users. */
    private function sampleEmailFromPlan(int $planId): ?string
    {
        $total = $this->client->users()->count(mailboxplanId: $planId);
        if ($total < 1) {
            return null;
        }

        $offset = random_int(0, $total - 1);
        $users = $this->client->users()->list(limit: $offset . ',1', mailboxplanId: $planId);
        if ($users === []) {
            $users = $this->client->users()->list(limit: '0,1', mailboxplanId: $planId);
        }

        return $users[0] ?? null;
    }

    protected function domainExists(string $domain): bool
    {
        try {
            $this->client->domains()->list(domain: $domain);

            return true;
        } catch (NotFoundException) {
            return false;
        }
    }

    /**
     * Issue a raw GET straight at an endpoint, bypassing the SDK's client-side
     * validation, and return the HTTP status. For probing API-level responses
     * (e.g. 406) that the typed methods guard against before sending.
     *
     * @param array<string, scalar|null> $query
     */
    protected function rawStatus(string $path, array $query = []): int
    {
        return (new \GuzzleHttp\Client(['http_errors' => false]))->get($this->rawUrl($path, $query))->getStatusCode();
    }

    /**
     * Issue a raw GET and return the decoded JSON body — for asserting the exact
     * keys/shape the API returns. (The SDK's DTOs default missing keys, so a
     * typed assertion can't detect a dropped or renamed field.)
     */
    protected function rawJson(string $path, array $query = []): mixed
    {
        $body = (string) (new \GuzzleHttp\Client(['http_errors' => false]))->get($this->rawUrl($path, $query))->getBody();

        return json_decode($body, true);
    }

    /** @param array<string, scalar|null> $query */
    private function rawUrl(string $path, array $query = []): string
    {
        $url = rtrim($this->baseUri, '/') . '/' . rawurlencode($this->apiKey) . '/' . ltrim($path, '/');
        $params = array_filter($query, static fn (mixed $v): bool => $v !== null);
        if ($params !== []) {
            $url .= '?' . http_build_query($params);
        }

        return $url;
    }

    /**
     * Poll $condition until it returns true or the consistency timeout elapses.
     * An exception thrown by $condition counts as "not yet" (read-after-write
     * lag often surfaces as a transient 404), so callers can poll freely.
     */
    protected function waitUntil(callable $condition, ?int $timeoutSeconds = null): bool
    {
        $timeout = $timeoutSeconds ?? static::CONSISTENCY_TIMEOUT;
        for ($waited = 0; $waited <= $timeout; $waited += 2) {
            try {
                if ($condition()) {
                    return true;
                }
            } catch (\Throwable) {
                // treat as not-ready and keep polling
            }
            sleep(2);
        }

        try {
            return (bool) $condition();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Assert that $condition becomes true within the consistency timeout. On
     * timeout, fails with a description of what was awaited and the last error
     * the condition threw — far more useful than a bare "false is true".
     *
     * @param callable(): bool $condition
     */
    protected function assertEventually(callable $condition, string $description, ?int $timeoutSeconds = null): void
    {
        $timeout = $timeoutSeconds ?? static::CONSISTENCY_TIMEOUT;
        $lastError = null;
        for ($waited = 0; $waited <= $timeout; $waited += 2) {
            try {
                if ($condition()) {
                    self::assertTrue(true);

                    return;
                }
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
            }
            sleep(2);
        }

        self::fail(sprintf(
            'Timed out after %ds waiting for %s.%s',
            $timeout,
            $description,
            $lastError !== null ? " Last error: {$lastError}" : '',
        ));
    }

    /**
     * Perform a write that can transiently 404 ("... does not relate to a domain")
     * on a just-provisioned domain, retrying until the server accepts it (bounded
     * by CONSISTENCY_TIMEOUT). For the first writes touching a fresh TEST_DOMAIN —
     * a successful return is the success signal; any thrown error counts as
     * "not settled yet" and is retried.
     *
     * @param callable(): void $write
     */
    protected function assertWriteAcceptedEventually(callable $write, string $description): void
    {
        $this->assertEventually(static function () use ($write): bool {
            $write();

            return true;
        }, $description);
    }

    /** Register an arbitrary teardown callback (run LIFO, errors swallowed). */
    protected function defer(callable $fn): void
    {
        $this->cleanup[] = $fn;
    }

    protected function deferRemoveMailbox(string $email): void
    {
        $this->cleanup[] = fn () => $this->quietly(fn () => $this->client->users()->remove($email));
    }

    /**
     * Wait for the asynchronous deletion job behind /users/remove to finish.
     * remove() only queues the job and returns immediately, so the mailbox keeps
     * resolving (get 200) until a background worker completes it; poll for the
     * terminal "gone" state (get 404) on the deletion budget, not the much
     * shorter read-after-write one.
     */
    protected function assertMailboxDeleted(string $email): void
    {
        $this->assertEventually(
            fn () => $this->rawStatus('/users/list', ['user' => $email]) === 404,
            "the async deletion job for {$email} to complete (address stops resolving)",
            static::DELETION_TIMEOUT,
        );
    }

    protected function deferRemoveDomain(string $domain): void
    {
        $this->cleanup[] = fn () => $this->quietly(fn () => $this->client->domains()->remove($domain));
    }

    /** Run a call, swallowing any exception (used for idempotent setup/cleanup). */
    protected function quietly(callable $fn): void
    {
        try {
            $fn();
        } catch (\Throwable) {
            // ignore
        }
    }

    private static function resolveApiKey(): ?string
    {
        $key = getenv('MAILCORE_API_KEY') ?: null;
        if ($key !== null) {
            return $key;
        }

        $config = self::readConfig();

        return isset($config['api_key']) && is_string($config['api_key']) && $config['api_key'] !== ''
            ? $config['api_key']
            : null;
    }

    private static function resolveBaseUri(): string
    {
        $env = getenv('MAILCORE_BASE_URI');
        if ($env !== false && trim($env) !== '') {
            return $env;
        }

        $config = self::readConfig();

        return isset($config['base_uri']) && is_string($config['base_uri']) && $config['base_uri'] !== ''
            ? $config['base_uri']
            : MailcoreClient::DEFAULT_BASE_URI;
    }

    /** @return array<string, mixed> */
    private static function readConfig(): array
    {
        $base = getenv('XDG_CONFIG_HOME') ?: (getenv('HOME') . '/.config');
        $path = $base . '/mailcore/config.ini';
        if (! is_file($path) || ! is_readable($path)) {
            return [];
        }

        return parse_ini_file($path, false, INI_SCANNER_NORMAL) ?: [];
    }
}
