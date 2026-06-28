<?php

declare(strict_types=1);

namespace Inboxcom\Mailcore\Resource;

use Inboxcom\Mailcore\Http\Transport;
use Inboxcom\Mailcore\Model\Mailboxplan;
use OpenApi\Attributes as OA;

/** Operations on mailbox plans (the `mailboxplans` tag). */
final class Mailboxplans
{
    public function __construct(private readonly Transport $transport)
    {
    }

    /**
     * Return all available mailbox plans.
     *
     * @return list<Mailboxplan>
     */
    #[OA\Get(
        path: '/mailboxplans/list',
        operationId: 'listMailboxplans',
        summary: 'List',
        description: 'Return all available mailbox plans',
        tags: ['mailboxplans'],
        responses: [
            new OA\Response(response: 200, description: 'Listing all mailbox plans', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/Mailboxplan'))),
        ],
    )]
    public function list(): array
    {
        return array_values(array_map(
            static fn (mixed $row): Mailboxplan => Mailboxplan::fromArray((array) $row),
            (array) $this->transport->get('/mailboxplans/list'),
        ));
    }
}
