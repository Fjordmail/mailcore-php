<?php

declare(strict_types=1);

namespace Inboxcom\Mailcore\Resource;

use Inboxcom\Mailcore\Exception\ConflictException;
use Inboxcom\Mailcore\Exception\NotAcceptableException;
use Inboxcom\Mailcore\Exception\NotFoundException;
use Inboxcom\Mailcore\Http\Transport;
use Inboxcom\Mailcore\Model\FlagCount;
use Inboxcom\Mailcore\Model\FlaggedMailbox;
use Inboxcom\Mailcore\Model\Login;
use Inboxcom\Mailcore\Model\RestoreJob;
use Inboxcom\Mailcore\Model\Service;
use Inboxcom\Mailcore\Model\Snapshot;
use Inboxcom\Mailcore\Model\User;
use OpenApi\Attributes as OA;

/**
 * Operations on users / mailboxes (the `users` tag in the OpenAPI spec).
 *
 * The #[OA\*] attributes drive the generated OpenAPI document; parameters and
 * object schemas reference the reusable definitions in
 * {@see \Inboxcom\Mailcore\Doc\Parameters} and {@see \Inboxcom\Mailcore\Doc\Schemas}.
 */
final class Users
{
    public function __construct(private readonly Transport $transport)
    {
    }

    /**
     * List mailboxes, optionally narrowed.
     *
     * By default returns a list of e-mail address strings. Pass $extended = true
     * to get full {@see User} detail records instead (the API's `extended` flag).
     * Beware: extended returns a lot of data and the API marks it NOT intended
     * for automation.
     *
     * @param string|null $filter        Search filter, e.g. "*".
     * @param string|null $limit         Offset and limit, e.g. "0,100".
     * @param int|null    $mailboxplanId Restrict to a single mailbox plan.
     * @param bool         $extended      Return detail records instead of addresses.
     *
     * @return ($extended is true ? list<User> : list<string>)
     */
    #[OA\Get(
        path: '/users/list',
        operationId: 'listUsers',
        summary: 'List',
        description: 'Return all users, look up a specific user, or (extended) return detailed records',
        tags: ['users'],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/User'),
            new OA\Parameter(ref: '#/components/parameters/Limit'),
            new OA\Parameter(ref: '#/components/parameters/Filter'),
            new OA\Parameter(ref: '#/components/parameters/MailboxplanIdOpt'),
            new OA\Parameter(ref: '#/components/parameters/Extended'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Success — shape depends on the parameters (see examples)', content: new OA\JsonContent(
                oneOf: [
                    new OA\Schema(ref: '#/components/schemas/User'),
                    new OA\Schema(type: 'array', items: new OA\Items(ref: '#/components/schemas/Email')),
                    new OA\Schema(type: 'array', items: new OA\Items(ref: '#/components/schemas/User')),
                ],
                examples: [
                    new OA\Examples(example: 'Addresses', summary: 'Default — array of addresses', value: ['holger.demo.test@example.com', 'a.demo.test@example.com']),
                    new OA\Examples(example: 'SingleUser', summary: 'With ?user= — one detail record', value: ['active' => 1, 'imap' => 1, 'pop3' => 1, 'mailboxplan_name' => 'Demo Plan', 'mailboxplan_id' => 4]),
                    new OA\Examples(example: 'Extended', summary: 'With extended=1 — compact records', value: [['email' => 'holger.demo.test@example.com', 'active' => 1, 'mailboxplan_id' => 4]]),
                ],
            )),
            new OA\Response(response: 404, description: 'User not found', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ],
    )]
    public function list(?string $filter = null, ?string $limit = null, ?int $mailboxplanId = null, bool $extended = false): array
    {
        $rows = (array) $this->transport->get('/users/list', [
            'filter' => $filter,
            'limit' => $limit,
            'mailboxplan_id' => $mailboxplanId,
            'extended' => $extended ? 1 : null,
        ]);

        if (! $extended) {
            /** @var list<string> $rows */
            return array_values($rows);
        }

        return array_values(array_map(
            static function (mixed $row): User {
                $data = (array) $row;

                return User::fromArray((string) ($data['email'] ?? ''), $data);
            },
            $rows,
        ));
    }

    /**
     * Fetch the full detail record for a single mailbox.
     *
     * (Documented as part of the /users/list operation via the `user` parameter.)
     *
     * @throws \Inboxcom\Mailcore\Exception\NotFoundException if the user does not exist.
     */
    public function get(string $email): User
    {
        /** @var array<string, mixed> $data */
        $data = (array) $this->transport->get('/users/list', ['user' => $email]);

        return User::fromArray($email, $data);
    }

    /** Count users in a domain or mailbox plan. The two filters are mutually exclusive. */
    #[OA\Get(
        path: '/users/countusers',
        operationId: 'countUsers',
        summary: 'Count users',
        description: 'Count users, filtered by either domain or mailbox plan (not both)',
        tags: ['users'],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/DomainNameOpt'),
            new OA\Parameter(ref: '#/components/parameters/MailboxplanIdOpt'),
        ],
        responses: [new OA\Response(response: 200, description: 'User count', content: new OA\JsonContent(type: 'integer', format: 'int32', minimum: 0))],
    )]
    public function count(?string $domain = null, ?int $mailboxplanId = null): int
    {
        return (int) $this->transport->get('/users/countusers', [
            'domain' => $domain,
            'mailboxplan_id' => $mailboxplanId,
        ]);
    }

    /**
     * The last 20 unique public IPs that authenticated against this mailbox.
     *
     * @return list<Login>
     */
    #[OA\Get(
        path: '/users/detailedlastlogin',
        operationId: 'detailedLastLogin',
        summary: 'Detailed last logins',
        description: 'The last 20 unique public IPs that authenticated against the mailbox',
        tags: ['users'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/Email')],
        responses: [
            new OA\Response(response: 200, description: 'Listing detailed last logins', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/Login'))),
            new OA\Response(response: 404, description: 'User not found', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 417, description: 'E-mail address not valid', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ],
    )]
    public function detailedLastLogin(string $email): array
    {
        return $this->mapLogins($this->transport->get('/users/detailedlastlogin', ['email' => $email]));
    }

    /**
     * All logins across the service in the last 10 minutes.
     *
     * @return list<Login>
     */
    #[OA\Get(
        path: '/users/latestlogins',
        operationId: 'latestLogins',
        summary: 'Latest logins',
        description: 'All successful logins in the last 10 minutes, globally or per mailbox plan',
        tags: ['users'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/MailboxplanIdOpt')],
        responses: [
            new OA\Response(response: 200, description: 'Listing latest logins', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/Login'))),
            new OA\Response(response: 417, description: 'Invalid mailboxplan_id syntax', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ],
    )]
    public function latestLogins(?int $mailboxplanId = null): array
    {
        return $this->mapLogins($this->transport->get('/users/latestlogins', ['mailboxplan_id' => $mailboxplanId]));
    }

    /**
     * Users who have not logged in since the given date (for cleanup).
     *
     * @param string $date Date in `YYYY-MM-DD` form, e.g. "2025-02-21". The live
     *                     API rejects a date that includes a time component.
     *
     * @return list<Login>
     */
    #[OA\Get(
        path: '/users/withlastloginbefore',
        operationId: 'listWithLastLoginBefore',
        summary: 'List with no logins since given date',
        description: 'Users with no login since the given date (date-only; a date-time is rejected)',
        tags: ['users'],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/Date'),
            new OA\Parameter(ref: '#/components/parameters/MailboxplanIdOpt'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Listing users', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/Login'))),
            new OA\Response(response: 411, description: 'Date required', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 417, description: 'Date invalid syntax', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ],
    )]
    public function listWithLastLoginBefore(string $date, ?int $mailboxplanId = null): array
    {
        return $this->mapLogins($this->transport->get('/users/withlastloginbefore', [
            'date' => $date,
            'mailboxplan_id' => $mailboxplanId,
        ]));
    }

    /**
     * Whether an address is available for a new user/alias.
     *
     * Returns false both when the address is already taken (409) and when it is
     * not allowed by policy (406); a malformed address (417) still throws.
     */
    #[OA\Get(
        path: '/users/checkavailability',
        operationId: 'checkAvailability',
        summary: 'Check e-mail address availability',
        description: 'Check that an e-mail address is available for a new user or alias',
        tags: ['users'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/Email')],
        responses: [
            new OA\Response(response: 200, description: 'E-mail address available'),
            new OA\Response(response: 406, description: 'E-mail address not allowed', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 409, description: 'E-mail address not available', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 417, description: 'E-mail address not valid', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ],
    )]
    public function isAvailable(string $email): bool
    {
        try {
            $this->transport->get('/users/checkavailability', ['email' => $email]);

            return true;
        } catch (ConflictException | NotAcceptableException) {
            return false;
        }
    }

    /**
     * Whether an address is reserved (a removed mailbox held back under the
     * identity-theft policy). A malformed address (417) throws.
     */
    #[OA\Get(
        path: '/users/checkreservation',
        operationId: 'checkReservation',
        summary: 'Check e-mail address reservation',
        description: 'Check if an e-mail address is reserved and so unavailable for a new mailbox',
        tags: ['users'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/Email')],
        responses: [
            new OA\Response(response: 200, description: 'E-mail address is reserved', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 404, description: 'E-mail address is not reserved, or belongs to an existing mailbox', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 417, description: 'E-mail address not valid', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ],
    )]
    public function isReserved(string $email): bool
    {
        // Live contract: 200 == reserved, 404 == not reserved (or an existing
        // mailbox), 417 == invalid address (propagates).
        try {
            $this->transport->get('/users/checkreservation', ['email' => $email]);

            return true;
        } catch (NotFoundException) {
            return false;
        }
    }

    /**
     * Add a new mailbox.
     *
     * @param bool $deactivated       Create in an initially deactivated state.
     * @param bool $ignoreReservation Add even if the address is reserved.
     */
    #[OA\Get(
        path: '/users/add',
        operationId: 'addUser',
        summary: 'Add',
        description: 'Adds a new user to the mail service',
        tags: ['users'],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/Email'),
            new OA\Parameter(ref: '#/components/parameters/Password'),
            new OA\Parameter(ref: '#/components/parameters/MailboxplanId'),
            new OA\Parameter(ref: '#/components/parameters/IgnoreReservation'),
            new OA\Parameter(ref: '#/components/parameters/Deactivated'),
        ],
        responses: [
            new OA\Response(response: 201, description: 'User added successfully'),
            new OA\Response(response: 404, description: 'E-mail address does not relate to a domain / no such mailbox plan', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 406, description: 'Password complexity not met', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 409, description: 'User already exists', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 410, description: 'E-mail address is reserved', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 411, description: 'Password required', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 417, description: 'E-mail address not valid', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ],
    )]
    public function add(
        string $email,
        string $password,
        int $mailboxplanId,
        bool $deactivated = false,
        bool $ignoreReservation = false,
    ): void {
        // These flags are presence-only: the API ignores the value and treats
        // any occurrence as true, so we send 1 or omit entirely.
        $this->transport->get('/users/add', [
            'email' => $email,
            'password' => $password,
            'mailboxplan' => $mailboxplanId,
            'deactivated' => $deactivated ? 1 : null,
            'ignorereservation' => $ignoreReservation ? 1 : null,
        ]);
    }

    /**
     * Remove a mailbox and all its data.
     *
     * The address is reserved afterwards (identity-theft policy) unless later
     * re-added with ignoreReservation.
     */
    #[OA\Get(
        path: '/users/remove',
        operationId: 'removeUser',
        summary: 'Remove',
        description: 'Remove a user from the mail service. Removes all data; the address is reserved.',
        tags: ['users'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/Email')],
        responses: [
            new OA\Response(response: 200, description: 'User removed'),
            new OA\Response(response: 400, description: 'Bad request', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 404, description: 'User not found', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ],
    )]
    public function remove(string $email): void
    {
        $this->transport->get('/users/remove', ['email' => $email]);
    }

    /** Add an alias for a user's primary address. */
    #[OA\Get(
        path: '/users/addalias',
        operationId: 'addAlias',
        summary: 'Add alias',
        description: "Adds an alias for a user's primary e-mail address",
        tags: ['users'],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/Email'),
            new OA\Parameter(ref: '#/components/parameters/Alias'),
            new OA\Parameter(ref: '#/components/parameters/IgnoreReservation'),
        ],
        responses: [
            new OA\Response(response: 201, description: 'Alias added successfully'),
            new OA\Response(response: 404, description: 'Source/destination does not relate to a domain, or does not exist', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 409, description: 'Alias already exists', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 410, description: 'E-mail address is reserved', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ],
    )]
    public function addAlias(string $email, string $alias, bool $ignoreReservation = false): void
    {
        $this->transport->get('/users/addalias', [
            'email' => $email,
            'alias' => $alias,
            'ignorereservation' => $ignoreReservation ? 1 : null,
        ]);
    }

    /** Remove an alias. The alias address becomes reserved afterwards. */
    #[OA\Get(
        path: '/users/removealias',
        operationId: 'removeAlias',
        summary: 'Remove alias',
        description: 'Remove an alias. The address is reserved afterwards.',
        tags: ['users'],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/Email'),
            new OA\Parameter(ref: '#/components/parameters/Alias'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Alias removed'),
            new OA\Response(response: 400, description: 'Bad request', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 404, description: 'Alias not found', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ],
    )]
    public function removeAlias(string $email, string $alias): void
    {
        $this->transport->get('/users/removealias', ['email' => $email, 'alias' => $alias]);
    }

    /** Resolve an alias to its primary e-mail address. */
    #[OA\Get(
        path: '/users/lookupalias',
        operationId: 'lookupAlias',
        summary: 'Lookup alias',
        description: 'Lookup an alias and return the primary e-mail address',
        tags: ['users'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/Alias')],
        responses: [
            new OA\Response(response: 200, description: 'Alias exists', content: new OA\JsonContent(properties: [new OA\Property(property: 'user', ref: '#/components/schemas/Email')], type: 'object')),
            new OA\Response(response: 400, description: 'Bad request', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 404, description: 'Alias not found', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ],
    )]
    public function lookupAlias(string $alias): string
    {
        /** @var array<string, mixed> $data */
        $data = (array) $this->transport->get('/users/lookupalias', ['alias' => $alias]);

        // The primary address comes back under the `user` key.
        return (string) ($data['user'] ?? '');
    }

    /**
     * List a user's alias addresses. There is no dedicated endpoint — aliases are
     * read from the user record (/users/list?user=), so this is sugar over get().
     *
     * @return list<string>
     */
    public function listAliases(string $email): array
    {
        return $this->get($email)->aliases;
    }

    /**
     * Add a forwarding policy. To keep a local copy too, forward to the same
     * source and destination address.
     */
    #[OA\Get(
        path: '/users/addforward',
        operationId: 'addForward',
        summary: 'Add forward',
        description: 'Add a forwarding policy for a specific user',
        tags: ['users'],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/Email'),
            new OA\Parameter(ref: '#/components/parameters/Forward'),
        ],
        responses: [
            new OA\Response(response: 201, description: 'Forwarding policy added successfully'),
            new OA\Response(response: 404, description: 'Source e-mail address does not exist', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 409, description: 'Forwarding policy already exists', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ],
    )]
    public function addForward(string $email, string $forward): void
    {
        $this->transport->get('/users/addforward', ['email' => $email, 'forward' => $forward]);
    }

    /** Remove a forwarding policy. */
    #[OA\Get(
        path: '/users/removeforward',
        operationId: 'removeForward',
        summary: 'Remove forward',
        description: 'Remove a forwarding policy',
        tags: ['users'],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/Email'),
            new OA\Parameter(ref: '#/components/parameters/Forward'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Forward policy removed'),
            new OA\Response(response: 400, description: 'Bad request', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 404, description: 'Forward policy not found', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ],
    )]
    public function removeForward(string $email, string $forward): void
    {
        $this->transport->get('/users/removeforward', ['email' => $email, 'forward' => $forward]);
    }

    /**
     * List a user's forwarding destinations. Read from the user record
     * (/users/list?user=); sugar over get(), as there is no dedicated endpoint.
     *
     * @return list<string>
     */
    public function listForwards(string $email): array
    {
        return $this->get($email)->forwards;
    }

    /**
     * Test whether a plaintext password meets the complexity policy. If $email
     * is given, password re-use is also checked.
     *
     * Returns nothing on success; the failure modes surface as exceptions so the
     * caller can tell them apart:
     *
     * @throws \Inboxcom\Mailcore\Exception\NotAcceptableException        complexity not met
     * @throws \Inboxcom\Mailcore\Exception\ConflictException             password used in the last 365 days
     * @throws \Inboxcom\Mailcore\Exception\MissingParameterException     no password supplied
     * @throws \Inboxcom\Mailcore\Exception\NotFoundException             the given user does not exist
     */
    #[OA\Get(
        path: '/users/testpasswordcomplexity',
        operationId: 'testPasswordComplexity',
        summary: 'Test password complexity',
        description: 'Test if a plaintext password meets the complexity policy (and re-use, if email given)',
        tags: ['users'],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/Password'),
            new OA\Parameter(ref: '#/components/parameters/EmailOpt'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Password meets complexity policy'),
            new OA\Response(response: 404, description: 'User not found', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 405, description: 'Password matches current password', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 406, description: 'Password complexity not met', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 409, description: 'Password has already been used the last 365 days', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 411, description: 'Password required', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ],
    )]
    public function testPasswordComplexity(string $password, ?string $email = null): void
    {
        $this->transport->get('/users/testpasswordcomplexity', ['password' => $password, 'email' => $email]);
    }

    /**
     * Set a new password. Also clears the spammer and weakpass flags unless
     * $noResetFlags is true.
     */
    #[OA\Get(
        path: '/users/newpassword',
        operationId: 'newPassword',
        summary: 'New password',
        description: 'Define a new password; also clears spammer and weakpass flags',
        tags: ['users'],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/Email'),
            new OA\Parameter(ref: '#/components/parameters/Password'),
            new OA\Parameter(ref: '#/components/parameters/NoResetFlags'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Password updated'),
            new OA\Response(response: 400, description: 'Bad request', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 404, description: 'User not found', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 405, description: 'Password matches current password', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 406, description: 'Password complexity requirement not met', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 409, description: 'Password has already been used the last 365 days', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 411, description: 'Password required', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ],
    )]
    public function newPassword(string $email, string $password, bool $noResetFlags = false): void
    {
        $this->transport->get('/users/newpassword', [
            'email' => $email,
            'password' => $password,
            'noresetflags' => $noResetFlags ? 1 : null,
        ]);
    }

    /**
     * Verify a plaintext password against the mailbox.
     *
     * Returns false on an incorrect password (the API's 406) rather than
     * throwing, so callers can use it as a boolean check.
     */
    #[OA\Get(
        path: '/users/verifypassword',
        operationId: 'verifyPassword',
        summary: 'Verify password',
        description: 'Test if a password is correct',
        tags: ['users'],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/Email'),
            new OA\Parameter(ref: '#/components/parameters/Password'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Password is correct'),
            new OA\Response(response: 406, description: 'Password is not correct', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 411, description: 'Password required', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ],
    )]
    public function verifyPassword(string $email, string $password): bool
    {
        try {
            $this->transport->get('/users/verifypassword', [
                'email' => $email,
                'password' => $password,
            ]);

            return true;
        } catch (NotAcceptableException) {
            return false;
        }
    }

    /** Retrieve the active Sieve script for a mailbox. */
    #[OA\Get(
        path: '/users/getactivesievescript',
        operationId: 'getActiveSieveScript',
        summary: 'Get active sieve script',
        description: 'Retrieve the active Sieve script for a specific user',
        tags: ['users'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/Email')],
        responses: [
            new OA\Response(response: 200, description: 'Success', content: new OA\JsonContent(ref: '#/components/schemas/SieveScript')),
            new OA\Response(response: 404, description: 'User not found, or the mailbox has no active sieve script', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ],
    )]
    public function getActiveSieveScript(string $email): string
    {
        return (string) $this->transport->get('/users/getactivesievescript', ['email' => $email]);
    }

    /** Move a mailbox to a different mailbox plan. */
    #[OA\Get(
        path: '/users/newmailboxplan',
        operationId: 'newMailboxPlan',
        summary: 'New mailbox plan',
        description: 'Change mailbox plan for a specific user',
        tags: ['users'],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/Email'),
            new OA\Parameter(ref: '#/components/parameters/MailboxplanId'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Mailbox plan updated'),
            new OA\Response(response: 404, description: 'User not found / mailbox plan not found', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ],
    )]
    public function newMailboxPlan(string $email, int $mailboxplanId): void
    {
        $this->transport->get('/users/newmailboxplan', ['email' => $email, 'mailboxplan' => $mailboxplanId]);
    }

    /** Activate all services for a mailbox. */
    #[OA\Get(
        path: '/users/activate',
        operationId: 'activateUser',
        summary: 'Activate',
        description: 'Activate all services for a specific user',
        tags: ['users'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/Email')],
        responses: [
            new OA\Response(response: 200, description: 'User activated'),
            new OA\Response(response: 404, description: 'User not found', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ],
    )]
    public function activate(string $email): void
    {
        $this->transport->get('/users/activate', ['email' => $email]);
    }

    /** Deactivate all services for a mailbox. */
    #[OA\Get(
        path: '/users/deactivate',
        operationId: 'deactivateUser',
        summary: 'Deactivate',
        description: 'Deactivate all services for a specific user',
        tags: ['users'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/Email')],
        responses: [
            new OA\Response(response: 200, description: 'User deactivated'),
            new OA\Response(response: 404, description: 'User not found', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ],
    )]
    public function deactivate(string $email): void
    {
        $this->transport->get('/users/deactivate', ['email' => $email]);
    }

    /** Activate or deactivate a mailbox depending on its current state. */
    #[OA\Get(
        path: '/users/toggleactive',
        operationId: 'toggleActive',
        summary: 'Toggle active',
        description: 'Activate or deactivate a user depending on the current state',
        tags: ['users'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/Email')],
        responses: [
            new OA\Response(response: 200, description: 'User activated/deactivated'),
            new OA\Response(response: 404, description: 'User not found', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ],
    )]
    public function toggleActive(string $email): void
    {
        $this->transport->get('/users/toggleactive', ['email' => $email]);
    }

    /**
     * Set the spam tolerance level (1 = tolerant, 5 = aggressive).
     */
    #[OA\Get(
        path: '/users/setspamtolerance',
        operationId: 'setSpamTolerance',
        summary: 'Set spam tolerance',
        description: 'Define the mail filter spam tolerance level (1 tolerant .. 5 aggressive)',
        tags: ['users'],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/Email'),
            new OA\Parameter(ref: '#/components/parameters/SpamToleranceScore'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Spam tolerance level updated'),
            new OA\Response(response: 404, description: 'User not found', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ],
    )]
    public function setSpamTolerance(string $email, int $score): void
    {
        $this->transport->get('/users/setspamtolerance', ['email' => $email, 'score' => $score]);
    }

    /**
     * Grant time-limited administrative access via a temporary password, and
     * return that password.
     *
     * NOTE: sensitive towards GDPR / code of conduct — restrict to admins.
     *
     * @param int|null    $timeWindow   Minutes until expiry (API default 10).
     * @param string|null $tempPassword Use a specific temporary password; when
     *                                  omitted the API generates a random one.
     *
     * @return string The temporary password now in effect — the one supplied,
     *                or the generated one when $tempPassword is null.
     */
    #[OA\Get(
        path: '/users/temporaryaccess',
        operationId: 'temporaryAccess',
        summary: 'Temporary access',
        description: 'Set a time-limited temporary password for administrative access (GDPR-sensitive). Returns the temporary password (generated when none is supplied).',
        tags: ['users'],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/Email'),
            new OA\Parameter(ref: '#/components/parameters/TimeWindow'),
            new OA\Parameter(ref: '#/components/parameters/TemporaryPassword'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Temporary password set; the body is that password', content: new OA\JsonContent(type: 'string')),
            new OA\Response(response: 404, description: 'User not found', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ],
    )]
    public function temporaryAccess(string $email, ?int $timeWindow = null, ?string $tempPassword = null): string
    {
        // The API's parameter is `password` (NOT `temppassword`, which it silently
        // ignores and generates a random one instead). The response body is the
        // resulting temporary password.
        return (string) $this->transport->get('/users/temporaryaccess', [
            'email' => $email,
            'timewindow' => $timeWindow,
            'password' => $tempPassword,
        ]);
    }

    /** Log a login into the mailbox's last-login table (e.g. from a remote webmail). */
    #[OA\Get(
        path: '/users/loglogin',
        operationId: 'logLogin',
        summary: 'Log login',
        description: "Submit an entry into the user's last-login table",
        tags: ['users'],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/Email'),
            new OA\Parameter(ref: '#/components/parameters/Service'),
            new OA\Parameter(ref: '#/components/parameters/IPv4'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'A user login was logged'),
            new OA\Response(response: 404, description: 'User not found', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 406, description: 'Service not recognized', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 411, description: 'Service or IP required', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 417, description: 'Invalid IP syntax', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ],
    )]
    public function logLogin(string $email, Service $service, string $ip): void
    {
        $this->transport->get('/users/loglogin', [
            'email' => $email,
            'service' => $service->value,
            'ip' => $ip,
        ]);
    }

    /** Set the maximum number of mails the mailbox may send per rolling 24 hours. */
    #[OA\Get(
        path: '/users/setmaxmailssentperday',
        operationId: 'setMaxMailsSentPerDay',
        summary: 'Set max e-mails sent per day',
        description: 'Set the maximum number of mails sent per rolling 24 hours',
        tags: ['users'],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/Email'),
            new OA\Parameter(ref: '#/components/parameters/MaxMailsSentPerDay'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Allowed mails sent per day updated'),
            new OA\Response(response: 404, description: 'User not found', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ],
    )]
    public function setMaxMailsSentPerDay(string $email, int $mailsPerDay): void
    {
        $this->transport->get('/users/setmaxmailssentperday', ['email' => $email, 'mailsperday' => $mailsPerDay]);
    }

    /** Reset the rolling outgoing-mail counter (restores sending after a limit hit). */
    #[OA\Get(
        path: '/users/resetmailssentperday',
        operationId: 'resetMailsSentPerDay',
        summary: 'Reset e-mails sent per day',
        description: 'Reset the outgoing mail counter for a mailbox',
        tags: ['users'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/Email')],
        responses: [
            new OA\Response(response: 200, description: 'Reset outgoing mail counter'),
            new OA\Response(response: 404, description: 'User not found', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ],
    )]
    public function resetMailsSentPerDay(string $email): void
    {
        $this->transport->get('/users/resetmailssentperday', ['email' => $email]);
    }

    /**
     * Available backup snapshots of a mailbox.
     *
     * @return list<Snapshot>
     */
    #[OA\Get(
        path: '/users/listsnapshots',
        operationId: 'listSnapshots',
        summary: 'List snapshots',
        description: 'List available backup snapshots of a mailbox',
        tags: ['users'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/Email')],
        responses: [
            new OA\Response(response: 200, description: 'Listing snapshots', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/Snapshot'))),
            new OA\Response(response: 404, description: 'User not found', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 417, description: 'E-mail address not valid', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 500, description: 'Snapshot list could not be returned', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ],
    )]
    public function listSnapshots(string $email): array
    {
        return array_values(array_map(
            static fn (mixed $row): Snapshot => Snapshot::fromArray((array) $row),
            (array) $this->transport->get('/users/listsnapshots', ['email' => $email]),
        ));
    }

    /**
     * Queue a restore of a snapshot (merged into the live mailbox). Only one
     * pending job per mailbox is allowed; completion is delivered via callback.
     */
    #[OA\Get(
        path: '/users/restoresnapshot',
        operationId: 'restoreSnapshot',
        summary: 'Restore snapshot',
        description: 'Queue a restore job for a specific snapshot (merged into the live mailbox)',
        tags: ['users'],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/Email'),
            new OA\Parameter(ref: '#/components/parameters/SnapshotSerial'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Restore job successfully added to queue'),
            new OA\Response(response: 404, description: 'User not found', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 409, description: 'Mailbox already has a pending or active restore job', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 410, description: 'Serial does not exist', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 411, description: 'Serial required', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ],
    )]
    public function restoreSnapshot(string $email, string $serial): void
    {
        $this->transport->get('/users/restoresnapshot', ['email' => $email, 'serial' => $serial]);
    }

    /**
     * The last 10 restore jobs for a mailbox (pending, active and finished).
     *
     * @return list<RestoreJob>
     */
    #[OA\Get(
        path: '/users/listrestorejobs',
        operationId: 'listRestoreJobs',
        summary: 'List restore jobs',
        description: 'List the last 10 restore jobs queued for a mailbox',
        tags: ['users'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/Email')],
        responses: [
            new OA\Response(response: 200, description: 'Listing restore jobs', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/RestoreJob'))),
            new OA\Response(response: 404, description: 'User not found', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 410, description: 'This mailbox has no history of restore jobs', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 500, description: 'Restore job list could not be returned', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ],
    )]
    public function listRestoreJobs(string $email): array
    {
        return array_values(array_map(
            static fn (mixed $row): RestoreJob => RestoreJob::fromArray((array) $row),
            (array) $this->transport->get('/users/listrestorejobs', ['email' => $email]),
        ));
    }

    /**
     * All custom flags currently set, with a count of mailboxes per flag.
     *
     * @return list<FlagCount>
     */
    #[OA\Get(
        path: '/users/listflags',
        operationId: 'listFlags',
        summary: 'List flags',
        description: 'List all custom flags set on mailboxes, with a count per flag',
        tags: ['users'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/MailboxplanIdOpt')],
        responses: [
            new OA\Response(response: 200, description: 'Success', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/FlagCount'))),
            new OA\Response(response: 417, description: 'Invalid mailboxplan_id syntax', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ],
    )]
    public function listFlags(?int $mailboxplanId = null): array
    {
        return array_values(array_map(
            static fn (mixed $row): FlagCount => FlagCount::fromArray((array) $row),
            (array) $this->transport->get('/users/listflags', ['mailboxplan_id' => $mailboxplanId]),
        ));
    }

    /**
     * All mailboxes carrying a specific flag.
     *
     * @return list<FlaggedMailbox>
     */
    #[OA\Get(
        path: '/users/listflagged',
        operationId: 'listFlagged',
        summary: 'List flagged',
        description: 'List all mailboxes that have a specific flag set',
        tags: ['users'],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/Flag'),
            new OA\Parameter(ref: '#/components/parameters/MailboxplanIdOpt'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Success', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/FlaggedMailbox'))),
            new OA\Response(response: 411, description: 'Flag required', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 417, description: 'Invalid mailboxplan_id syntax', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ],
    )]
    public function listFlagged(string $flag, ?int $mailboxplanId = null): array
    {
        return array_values(array_map(
            static fn (mixed $row): FlaggedMailbox => FlaggedMailbox::fromArray((array) $row),
            (array) $this->transport->get('/users/listflagged', ['flag' => $flag, 'mailboxplan_id' => $mailboxplanId]),
        ));
    }

    /**
     * Set a custom flag on a mailbox. The special `spammer` flag blocks all
     * outgoing mail; `delete` is reserved for systematic routines.
     */
    #[OA\Get(
        path: '/users/setflag',
        operationId: 'setFlag',
        summary: 'Set flag',
        description: 'Set a custom flag on the mailbox (the special spammer flag blocks outgoing mail)',
        tags: ['users'],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/Email'),
            new OA\Parameter(ref: '#/components/parameters/Flag'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Flag set'),
            new OA\Response(response: 403, description: 'Flag is reserved for special use', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 404, description: 'User not found', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 411, description: 'Flag required', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ],
    )]
    public function setFlag(string $email, string $flag): void
    {
        $this->transport->get('/users/setflag', ['email' => $email, 'flag' => $flag]);
    }

    /** Remove a flag from a mailbox. */
    #[OA\Get(
        path: '/users/unflag',
        operationId: 'unflag',
        summary: 'Unflag',
        description: 'Remove a flag from a mailbox',
        tags: ['users'],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/Email'),
            new OA\Parameter(ref: '#/components/parameters/Flag'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Flag removed'),
            new OA\Response(response: 403, description: 'Flag is reserved for special use', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 404, description: 'User not found', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 411, description: 'Flag required', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ],
    )]
    public function unflag(string $email, string $flag): void
    {
        $this->transport->get('/users/unflag', ['email' => $email, 'flag' => $flag]);
    }

    /**
     * @return list<Login>
     */
    private function mapLogins(mixed $rows): array
    {
        return array_values(array_map(
            static fn (mixed $row): Login => Login::fromArray((array) $row),
            (array) $rows,
        ));
    }
}
