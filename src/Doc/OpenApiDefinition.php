<?php

declare(strict_types=1);

namespace Inboxcom\Mailcore\Doc;

use OpenApi\Attributes as OA;

/**
 * Document-level OpenAPI metadata: info, server, and the primitive shared
 * schemas. Object schemas live on their DTO classes; reusable query parameters
 * live in {@see Parameters}.
 *
 * This class carries no runtime behaviour — it only hosts attributes that
 * `bin/generate-openapi.php` reads. swagger-php is a dev dependency, so these
 * attributes are inert in production.
 */

#[OA\Info(
    version: '1.23',
    title: 'Mailcore API',
    description: "Developers who wish to integrate the functionality of the MailCore backend, to a third-party application. "
        . "Developers should be familiar with basic API functionality and fetching, posting and processing of JSON response.\n\n"
        . "The available functions are general everyday administration of users and domains across your services, "
        . "similar to those found in the MailCore Backoffice.",
    # contact: new OA\Contact(email: 'info@example.com'),
)]

// #[OA\ExternalDocumentation(description: 'Original PDF from Mailcore', url: 'https://...')]

#[OA\Server(
    url: 'https://api.example.com/{apiKey}',
    description: 'Mailcore API',
    variables: [
        new OA\ServerVariable(serverVariable: 'apiKey', default: 'XXX', description: 'Mailcore API key'),
    ],
)]

#[OA\Schema(schema: 'Email', type: 'string', format: 'email', description: 'E-mail address', example: 'holger.danske@example.com')]
#[OA\Schema(schema: 'Domain', type: 'string', description: 'Domain name', example: 'example.com')]
#[OA\Schema(schema: 'IPv4', type: 'string', format: 'ipv4', description: 'IPv4 address', example: '8.8.8.8')]
#[OA\Schema(schema: 'MailboxplanId', type: 'integer', format: 'int32', minimum: 0, description: 'Mailbox plan ID', example: 4)]
#[OA\Schema(schema: 'SieveScript', type: 'string', description: 'Sieve script')]
#[OA\Schema(
    schema: 'Error',
    type: 'object',
    required: ['statusmsg'],
    properties: [new OA\Property(property: 'statusmsg', type: 'string', description: 'Error message')],
)]

#[OA\Tag(name: 'domains', description: 'Operations on domains')]
#[OA\Tag(name: 'users', description: 'Operations on users')]
#[OA\Tag(name: 'mailboxplans', description: 'Operations on mailbox plans')]
#[OA\Tag(name: 'mailfilter', description: 'Operations on mail filters')]
#[OA\Tag(name: 'reports', description: 'Generate reports on users')]
#[OA\Tag(name: 'datadump', description: 'Data dump functions')]
final class OpenApiDefinition
{
}
