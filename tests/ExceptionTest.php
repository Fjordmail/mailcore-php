<?php

declare(strict_types=1);

namespace Inboxcom\Mailcore\Tests;

use Inboxcom\Mailcore\Exception\ApiException;
use Inboxcom\Mailcore\Exception\BadRequestException;
use Inboxcom\Mailcore\Exception\ConflictException;
use Inboxcom\Mailcore\Exception\ExpectationFailedException;
use Inboxcom\Mailcore\Exception\GoneException;
use Inboxcom\Mailcore\Exception\MailcoreException;
use Inboxcom\Mailcore\Exception\MissingParameterException;
use Inboxcom\Mailcore\Exception\NotAcceptableException;
use Inboxcom\Mailcore\Exception\NotFoundException;
use Inboxcom\Mailcore\Exception\ServerException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ExceptionTest extends TestCase
{
    /** @return iterable<string, array{int, class-string<ApiException>}> */
    public static function statusProvider(): iterable
    {
        yield '400' => [400, BadRequestException::class];
        yield '404' => [404, NotFoundException::class];
        yield '406' => [406, NotAcceptableException::class];
        yield '409' => [409, ConflictException::class];
        yield '410' => [410, GoneException::class];
        yield '411' => [411, MissingParameterException::class];
        yield '417' => [417, ExpectationFailedException::class];
        yield '500' => [500, ServerException::class];
        yield '503' => [503, ServerException::class];
        yield 'unmapped 4xx falls back to base' => [451, ApiException::class];
    }

    #[DataProvider('statusProvider')]
    public function testFromResponseMapsStatusToClass(int $status, string $expected): void
    {
        $e = ApiException::fromResponse($status, 'some message', '/users/add');

        self::assertInstanceOf($expected, $e);
        self::assertInstanceOf(MailcoreException::class, $e);
        self::assertSame($status, $e->statusCode);
        self::assertSame('some message', $e->statusMsg);
        self::assertSame('/users/add', $e->path);
    }

    public function testMessageContainsStatusReasonAndPath(): void
    {
        $e = ApiException::fromResponse(409, 'User already exists', '/users/add');

        self::assertStringContainsString('409', $e->getMessage());
        self::assertStringContainsString('User already exists', $e->getMessage());
        self::assertStringContainsString('/users/add', $e->getMessage());
    }

    public function testNullStatusMsgGetsAFallbackMessage(): void
    {
        $e = ApiException::fromResponse(500, null, '/datadump/fetch_latest');

        self::assertNull($e->statusMsg);
        self::assertStringContainsString('Mailcore API request failed', $e->getMessage());
    }

    public function testSubclassesShareTheBaseType(): void
    {
        $e = ApiException::fromResponse(404, 'User not found', '/users/list');

        self::assertInstanceOf(ApiException::class, $e);
        self::assertInstanceOf(\RuntimeException::class, $e);
    }
}
