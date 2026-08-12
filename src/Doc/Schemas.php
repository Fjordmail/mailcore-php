<?php

declare(strict_types=1);

namespace Inboxcom\Mailcore\Doc;

use OpenApi\Attributes as OA;

/**
 * Reusable object schemas (components/schemas), referenced from operation
 * responses. Kept central (like the curated spec) so the model classes stay
 * free of doc attributes. Property types describe the API wire format — note
 * booleans travel as 0/1 integers, which the SDK normalises to bool.
 *
 * Attribute-only holder; no runtime behaviour.
 */
#[OA\Schema(
    schema: 'User',
    type: 'object',
    properties: [
        new OA\Property(property: 'email', ref: '#/components/schemas/Email'),
        new OA\Property(property: 'active', type: 'integer', enum: [0, 1]),
        new OA\Property(property: 'imap', type: 'integer', enum: [0, 1]),
        new OA\Property(property: 'pop3', type: 'integer', enum: [0, 1]),
        new OA\Property(property: 'mailbox_quota', type: 'integer', format: 'int32', description: 'Quota (KiB)'),
        new OA\Property(property: 'mailbox_quota_override', type: 'integer', format: 'int32', nullable: true, description: 'Per-mailbox quota override (KiB), if set'),
        new OA\Property(property: 'mailboxplan_name', type: 'string'),
        new OA\Property(property: 'mailboxplan_id', type: 'integer', format: 'int32'),
        new OA\Property(property: 'date_created', type: 'string', format: 'date-time'),
        new OA\Property(property: 'last_login', type: 'string', description: 'Service;timestamp;ip triple, or a date-time'),
        new OA\Property(property: 'spammer', type: 'integer', enum: [0, 1]),
        new OA\Property(property: 'weakpass', type: 'integer', enum: [0, 1]),
        new OA\Property(property: 'allowed_mails_sent_per_day', type: 'integer', format: 'int32'),
        new OA\Property(property: 'mails_sent_current_day', type: 'integer', format: 'int32'),
        new OA\Property(property: 'mailbox_messages', type: 'integer', format: 'int32'),
        new OA\Property(property: 'mailbox_usage', type: 'number', format: 'float', description: 'KiB'),
        new OA\Property(property: 'mailbox_quotapct', type: 'number', format: 'float'),
        new OA\Property(property: 'days_over_quota', type: 'integer', format: 'int32'),
        new OA\Property(property: 'flags', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'password_changes', type: 'array', items: new OA\Items(type: 'string'), description: 'Recent password-change timestamps'),
        new OA\Property(property: 'forwards', type: 'array', items: new OA\Items(ref: '#/components/schemas/Email'), description: 'Forwarding destinations'),
        new OA\Property(property: 'aliases', type: 'array', items: new OA\Items(ref: '#/components/schemas/Email'), description: 'Alias addresses'),
        new OA\Property(property: 'spamtolerance', type: 'integer', format: 'int32', minimum: 1, maximum: 5),
    ],
)]
#[OA\Schema(
    schema: 'Mailboxplan',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', ref: '#/components/schemas/MailboxplanId'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'mailbox_quota', type: 'integer', format: 'int32', description: 'Quota (KiB)'),
        new OA\Property(property: 'imap', type: 'integer', enum: [0, 1]),
        new OA\Property(property: 'pop3', type: 'integer', enum: [0, 1]),
        new OA\Property(property: 'smtp', type: 'integer', enum: [0, 1]),
        new OA\Property(property: 'webmail', type: 'integer', enum: [0, 1]),
        new OA\Property(property: 'aliases', type: 'integer', format: 'int32'),
        new OA\Property(property: 'forwards', type: 'integer', format: 'int32'),
        new OA\Property(property: 'date_created', type: 'string', format: 'date-time'),
    ],
)]
#[OA\Schema(
    schema: 'BplListing',
    type: 'object',
    description: "BPL block details for a host, in the 409 body of /mailfilter/bpllookup.",
    properties: [
        new OA\Property(property: 'statusmsg', type: 'string', example: 'Host found on BPL'),
        new OA\Property(property: 'date_added', type: 'string', format: 'date-time', description: 'When the host was blocked'),
        new OA\Property(property: 'timeframe_min', type: 'integer', format: 'int32', description: 'Minutes the abuse spanned before the ban'),
        new OA\Property(property: 'sample', type: 'array', items: new OA\Items(type: 'string'), description: 'Sample usernames tried during the abuse'),
    ],
)]
#[OA\Schema(
    schema: 'Login',
    type: 'object',
    description: 'A login record; fields present vary by endpoint.',
    properties: [
        new OA\Property(property: 'email', ref: '#/components/schemas/Email'),
        new OA\Property(property: 'ip', ref: '#/components/schemas/IPv4'),
        new OA\Property(property: 'service', type: 'string', example: 'IMAP'),
        new OA\Property(property: 'timestamp', type: 'string', format: 'date-time', nullable: true),
    ],
)]
#[OA\Schema(
    schema: 'Snapshot',
    type: 'object',
    properties: [
        new OA\Property(property: 'serial', type: 'string'),
        new OA\Property(property: 'timestamp', type: 'string', format: 'date-time'),
        new OA\Property(property: 'size', type: 'string', example: '50 MB'),
    ],
)]
#[OA\Schema(
    schema: 'RestoreJob',
    type: 'object',
    properties: [
        new OA\Property(property: 'snapshot_date', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'date_queued', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'date_started', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'date_finished', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'status', type: 'string', enum: ['PENDING', 'SUCCESS', 'ERROR']),
        new OA\Property(property: 'mails_restored', type: 'integer', format: 'int32', minimum: 0),
        new OA\Property(property: 'mails_ignored', type: 'integer', format: 'int32', minimum: 0),
    ],
)]
#[OA\Schema(
    schema: 'FlagCount',
    type: 'object',
    properties: [
        new OA\Property(property: 'flag', type: 'string'),
        new OA\Property(property: 'count', type: 'integer', format: 'int32', minimum: 0),
    ],
)]
#[OA\Schema(
    schema: 'FlaggedMailbox',
    type: 'object',
    properties: [
        new OA\Property(property: 'email', ref: '#/components/schemas/Email'),
        new OA\Property(property: 'date_set', type: 'string', format: 'date-time'),
    ],
)]
#[OA\Schema(
    schema: 'SpamFlag',
    type: 'object',
    properties: [
        new OA\Property(property: 'email', ref: '#/components/schemas/Email'),
        new OA\Property(property: 'flag', type: 'string'),
        new OA\Property(property: 'date_set', type: 'string', format: 'date-time'),
    ],
)]
#[OA\Schema(
    schema: 'SmtpLimitHit',
    type: 'object',
    properties: [
        new OA\Property(property: 'email', ref: '#/components/schemas/Email'),
        new OA\Property(property: 'ip', ref: '#/components/schemas/IPv4'),
        new OA\Property(property: 'last_hit', type: 'string', format: 'date-time'),
    ],
)]
#[OA\Schema(
    schema: 'Asn',
    type: 'object',
    properties: [
        new OA\Property(property: 'asn', type: 'integer', format: 'int32', minimum: 0),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'country', type: 'string', description: 'ISO 3166-1 alpha-2'),
    ],
)]
#[OA\Schema(
    schema: 'SuspiciousMailboxHit',
    type: 'object',
    properties: [
        new OA\Property(property: 'email', ref: '#/components/schemas/Email'),
        new OA\Property(property: 'n_asn', type: 'integer', format: 'int32', minimum: 0),
        new OA\Property(property: 'n_countries', type: 'integer', format: 'int32', minimum: 0),
        new OA\Property(property: 'n_ips', type: 'integer', format: 'int32', minimum: 0),
        new OA\Property(property: 'countries', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'asns', type: 'array', items: new OA\Items(ref: '#/components/schemas/Asn')),
    ],
)]
#[OA\Schema(
    schema: 'SuspiciousMailboxActivityReport',
    type: 'object',
    properties: [
        new OA\Property(property: 'scanned_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'days', type: 'integer', format: 'int32', minimum: 1),
        new OA\Property(property: 'min_asns', type: 'integer', format: 'int32', minimum: 1),
        new OA\Property(property: 'skip_flags', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'hits', type: 'array', items: new OA\Items(ref: '#/components/schemas/SuspiciousMailboxHit')),
    ],
)]
final class Schemas
{
}
