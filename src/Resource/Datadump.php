<?php

declare(strict_types=1);

namespace Inboxcom\Mailcore\Resource;

use Inboxcom\Mailcore\Http\Transport;
use OpenApi\Attributes as OA;

/** Data dump operations (the `datadump` tag). */
final class Datadump
{
    public function __construct(private readonly Transport $transport)
    {
    }

    /**
     * Fetch the latest data dump: PGP-encrypted, gzip-compressed strategic data.
     *
     * Returned as a raw binary string (the endpoint is application/octet-stream,
     * not JSON); decrypt and decompress it downstream.
     *
     * Access is gated server-side (by API key / source IP). When the caller is
     * not permitted the endpoint answers HTTP 200 with the literal body
     * `Not allowed!` rather than an error status, so check for that before
     * treating the result as dump bytes.
     */
    #[OA\Get(
        path: '/datadump/fetch_latest',
        operationId: 'fetchLatestDataDump',
        summary: 'Fetch latest data dump',
        description: 'Fetch the latest PGP-encrypted, gzip-compressed data dump (access is server-gated by API key / source IP).',
        tags: ['datadump'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'The data dump, or — when the key/IP is not permitted — the plain body "Not allowed!" (still 200, not 403). See examples.',
                content: new OA\MediaType(
                    mediaType: 'application/octet-stream',
                    schema: new OA\Schema(type: 'string', format: 'binary'),
                    examples: [
                        new OA\Examples(example: 'DataDump', summary: 'Permitted', description: 'PGP-encrypted, gzip-compressed bytes', value: '<binary PGP+gzip data>'),
                        new OA\Examples(example: 'NotAllowed', summary: 'Not permitted (key/IP gated)', value: 'Not allowed!'),
                    ],
                ),
            ),
        ],
    )]
    public function fetchLatest(): string
    {
        return $this->transport->getRaw('/datadump/fetch_latest');
    }
}
