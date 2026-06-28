<?php

declare(strict_types=1);

namespace Inboxcom\Mailcore\Resource;

use Inboxcom\Mailcore\Http\Transport;
use Inboxcom\Mailcore\Model\SuspiciousMailboxActivityReport;
use OpenApi\Attributes as OA;

/** Reporting operations (the `reports` tag). */
final class Reports
{
    public function __construct(private readonly Transport $transport)
    {
    }

    /**
     * Mailboxes with potentially suspicious login patterns (an unusually high
     * number of unique ASNs in recent login history).
     */
    #[OA\Get(
        path: '/reports/suspicious_mailbox_activity',
        operationId: 'suspiciousMailboxActivity',
        summary: 'Suspicious mailbox activity',
        description: 'Mailboxes showing suspicious login patterns (high number of unique ASNs)',
        tags: ['reports'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/MailboxplanIdOpt')],
        responses: [
            new OA\Response(response: 200, description: 'Report generated successfully', content: new OA\JsonContent(ref: '#/components/schemas/SuspiciousMailboxActivityReport')),
            new OA\Response(response: 417, description: 'Invalid mailboxplan_id syntax', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ],
    )]
    public function suspiciousMailboxActivity(?int $mailboxplanId = null): SuspiciousMailboxActivityReport
    {
        return SuspiciousMailboxActivityReport::fromArray(
            (array) $this->transport->get('/reports/suspicious_mailbox_activity', ['mailboxplan_id' => $mailboxplanId]),
        );
    }
}
