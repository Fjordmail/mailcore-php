<?php

declare(strict_types=1);

namespace Inboxcom\Mailcore\Resource;

use Inboxcom\Mailcore\Http\Transport;
use OpenApi\Attributes as OA;

/**
 * Operations on domains (the `domains` tag in the OpenAPI spec).
 *
 * The #[OA\*] attributes are the single source of truth for the generated
 * OpenAPI document (see bin/generate-openapi.php). Parameters reference the
 * reusable definitions in {@see \Inboxcom\Mailcore\Doc\Parameters}.
 */
final class Domains
{
    public function __construct(private readonly Transport $transport)
    {
    }

    /**
     * List all domain names, optionally narrowed.
     *
     * @param string|null $domain Look up a single domain instead of listing all.
     * @param string|null $limit  Offset and limit, e.g. "0,100".
     * @param string|null $filter Search filter, e.g. "*".
     *
     * @return list<string>
     */
    #[OA\Get(
        path: '/domains/list',
        operationId: 'listDomains',
        summary: 'List',
        description: 'Return all domain names, or look up a specific domain',
        tags: ['domains'],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/DomainNameOpt'),
            new OA\Parameter(ref: '#/components/parameters/Limit'),
            new OA\Parameter(ref: '#/components/parameters/Filter'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Success', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/Domain'))),
            new OA\Response(response: 404, description: 'Domain name not found', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ],
    )]
    public function list(?string $domain = null, ?string $limit = null, ?string $filter = null): array
    {
        /** @var list<string> $result */
        $result = (array) $this->transport->get('/domains/list', [
            'domain' => $domain,
            'limit' => $limit,
            'filter' => $filter,
        ]);

        return $result;
    }

    /** Count all domains on the service. */
    #[OA\Get(
        path: '/domains/countdomains',
        operationId: 'countDomains',
        summary: 'Count domains',
        description: 'Return the total number of domains on the service. Takes no parameters.',
        tags: ['domains'],
        responses: [
            new OA\Response(response: 200, description: 'Domain count', content: new OA\JsonContent(type: 'integer', format: 'int32')),
        ],
    )]
    public function count(): int
    {
        return (int) $this->transport->get('/domains/countdomains');
    }

    /** Add a new domain to the mail service. */
    #[OA\Get(
        path: '/domains/add',
        operationId: 'addDomain',
        summary: 'Add',
        description: 'Adds a new domain to the mail service',
        tags: ['domains'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/DomainName')],
        responses: [
            new OA\Response(response: 201, description: 'Domain added successfully'),
            new OA\Response(response: 406, description: 'Domain name not valid', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 409, description: 'Domain name already exists', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ],
    )]
    public function add(string $domain): void
    {
        $this->transport->get('/domains/add', ['domain' => $domain]);
    }

    /** Add $alias as an alias domain for the existing $domain. */
    #[OA\Get(
        path: '/domains/addalias',
        operationId: 'addDomainAlias',
        summary: 'Add alias',
        description: 'Adds a new domain as an alias for an existing domain',
        tags: ['domains'],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/DomainName'),
            new OA\Parameter(ref: '#/components/parameters/DomainAlias'),
        ],
        responses: [
            new OA\Response(response: 201, description: 'Domain alias added successfully'),
            new OA\Response(response: 406, description: 'Domain name not valid', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 409, description: 'Domain name already exists', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ],
    )]
    public function addAlias(string $domain, string $alias): void
    {
        $this->transport->get('/domains/addalias', ['domain' => $domain, 'alias' => $alias]);
    }

    /** Remove a domain. Fails (406) if it still has mailboxes. */
    #[OA\Get(
        path: '/domains/remove',
        operationId: 'removeDomain',
        summary: 'Remove',
        description: 'Remove a domain from the mail service',
        tags: ['domains'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/DomainName')],
        responses: [
            new OA\Response(response: 200, description: 'Domain removed'),
            new OA\Response(response: 400, description: 'Bad request', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 404, description: 'Domain name not found', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 406, description: 'Cannot remove domain, when related users exists', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ],
    )]
    public function remove(string $domain): void
    {
        $this->transport->get('/domains/remove', ['domain' => $domain]);
    }
}
