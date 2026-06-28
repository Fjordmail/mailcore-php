<?php

declare(strict_types=1);

namespace Inboxcom\Mailcore\Doc;

use OpenApi\Attributes as OA;

/**
 * Reusable query parameters (components/parameters), referenced from operations
 * via `#/components/parameters/<name>`. Defined once here, like the curated spec.
 *
 * Attribute-only holder; no runtime behaviour.
 */
#[OA\Parameter(parameter: 'Alias', name: 'alias', in: 'query', required: true, description: 'E-mail address alias', schema: new OA\Schema(ref: '#/components/schemas/Email'))]
#[OA\Parameter(parameter: 'DomainAlias', name: 'alias', in: 'query', required: true, description: 'Alias domain (source)', schema: new OA\Schema(ref: '#/components/schemas/Domain'))]
#[OA\Parameter(parameter: 'Date', name: 'date', in: 'query', required: true, description: 'Date in YYYY-MM-DD form (a date-time is rejected by the API)', schema: new OA\Schema(type: 'string', example: '2025-02-21'))]
#[OA\Parameter(parameter: 'Deactivated', name: 'deactivated', in: 'query', required: false, description: 'Add the mailbox in a deactivated state (presence-only; any value is treated as true)', schema: new OA\Schema(type: 'integer', enum: [0, 1]))]
#[OA\Parameter(parameter: 'DomainName', name: 'domain', in: 'query', required: true, description: 'Domain name', schema: new OA\Schema(ref: '#/components/schemas/Domain'))]
#[OA\Parameter(parameter: 'DomainNameOpt', name: 'domain', in: 'query', required: false, description: 'Domain name', schema: new OA\Schema(ref: '#/components/schemas/Domain'))]
#[OA\Parameter(parameter: 'Email', name: 'email', in: 'query', required: true, description: 'E-mail address', schema: new OA\Schema(ref: '#/components/schemas/Email'))]
#[OA\Parameter(parameter: 'EmailOpt', name: 'email', in: 'query', required: false, description: 'E-mail address', schema: new OA\Schema(ref: '#/components/schemas/Email'))]
#[OA\Parameter(parameter: 'Extended', name: 'extended', in: 'query', required: false, description: 'Return detailed records for all users. Not intended for automation.', schema: new OA\Schema(type: 'integer', enum: [0, 1]))]
#[OA\Parameter(parameter: 'Filter', name: 'filter', in: 'query', required: false, description: 'Search filter; "*" is a wildcard', schema: new OA\Schema(type: 'string'))]
#[OA\Parameter(parameter: 'Flag', name: 'flag', in: 'query', required: true, description: 'Flag name', schema: new OA\Schema(type: 'string'))]
#[OA\Parameter(parameter: 'Forward', name: 'forward', in: 'query', required: true, description: 'E-mail address mails are forwarded to', schema: new OA\Schema(ref: '#/components/schemas/Email'))]
#[OA\Parameter(parameter: 'IgnoreReservation', name: 'ignorereservation', in: 'query', required: false, description: 'Add even if the address is reserved (presence-only; any value is treated as true)', schema: new OA\Schema(type: 'integer', enum: [0, 1]))]
#[OA\Parameter(parameter: 'IPv4', name: 'ip', in: 'query', required: true, description: 'User / client IPv4 address', schema: new OA\Schema(ref: '#/components/schemas/IPv4'))]
#[OA\Parameter(parameter: 'Limit', name: 'limit', in: 'query', required: false, description: 'Offset and limit, e.g. "0,100"', schema: new OA\Schema(type: 'string'))]
#[OA\Parameter(parameter: 'MailboxplanId', name: 'mailboxplan', in: 'query', required: true, description: 'The mailbox plan the user should belong to', schema: new OA\Schema(ref: '#/components/schemas/MailboxplanId'))]
#[OA\Parameter(parameter: 'MailboxplanIdOpt', name: 'mailboxplan_id', in: 'query', required: false, description: 'Restrict to a specific mailbox plan', schema: new OA\Schema(ref: '#/components/schemas/MailboxplanId'))]
#[OA\Parameter(parameter: 'MaxMailsSentPerDay', name: 'mailsperday', in: 'query', required: true, description: 'New outgoing daily limit', schema: new OA\Schema(type: 'integer', format: 'int32', minimum: 0))]
#[OA\Parameter(parameter: 'NoResetFlags', name: 'noresetflags', in: 'query', required: false, description: 'Do not reset flags on the mailbox (presence-only; any value is treated as true)', schema: new OA\Schema(type: 'integer', enum: [0, 1]))]
#[OA\Parameter(parameter: 'Password', name: 'password', in: 'query', required: true, description: 'Password (min 10 chars, 1 lower, 1 upper, 1 digit)', schema: new OA\Schema(type: 'string', format: 'password'))]
#[OA\Parameter(parameter: 'Recipient', name: 'recipient', in: 'query', required: true, description: 'Recipient address or domain wildcard (*@domain.com)', schema: new OA\Schema(ref: '#/components/schemas/Email'))]
#[OA\Parameter(parameter: 'Sender', name: 'sender', in: 'query', required: true, description: 'Sender address or domain wildcard (*@domain.com)', schema: new OA\Schema(ref: '#/components/schemas/Email'))]
#[OA\Parameter(parameter: 'Service', name: 'service', in: 'query', required: true, description: 'Protocol/service used for access', schema: new OA\Schema(type: 'string', enum: ['imap', 'pop3', 'webmail', 'smtp']))]
#[OA\Parameter(parameter: 'SnapshotSerial', name: 'serial', in: 'query', required: true, description: 'Snapshot serial (from /users/listsnapshots)', schema: new OA\Schema(type: 'string'))]
#[OA\Parameter(parameter: 'SpamToleranceScore', name: 'score', in: 'query', required: true, description: 'Spam tolerance level (1 tolerant .. 5 aggressive)', schema: new OA\Schema(type: 'integer', format: 'int32', minimum: 1, maximum: 5))]
#[OA\Parameter(parameter: 'TimeWindow', name: 'timewindow', in: 'query', required: false, description: 'Minutes until temporary access expires', schema: new OA\Schema(type: 'integer', format: 'int32', default: 10, minimum: 1, maximum: 365))]
#[OA\Parameter(parameter: 'TemporaryPassword', name: 'temppassword', in: 'query', required: false, description: 'Temporary password (a random one is generated if omitted)', schema: new OA\Schema(type: 'string', format: 'password'))]
#[OA\Parameter(parameter: 'User', name: 'user', in: 'query', required: false, description: 'E-mail address of a single user to look up', schema: new OA\Schema(ref: '#/components/schemas/Email'))]
final class Parameters
{
}
